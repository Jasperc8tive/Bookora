<?php
/**
 * Customer CRM application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Customers;

use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and persists customer profiles, manages notes & tags, and assembles
 * the activity timeline (notes + audit events) and booking history.
 */
final class CustomerManager {

	/**
	 * Customer repository.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $customers;

	/**
	 * Note repository.
	 *
	 * @var NoteRepository
	 */
	private NoteRepository $notes;

	/**
	 * Audit repository (timeline source).
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $audit_log;

	/**
	 * Audit logger.
	 *
	 * @var ActivityLogger
	 */
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param CustomerRepository $customers Customer repository.
	 * @param NoteRepository     $notes     Note repository.
	 * @param AuditLogRepository $audit_log Audit repository.
	 * @param ActivityLogger     $audit     Audit logger.
	 */
	public function __construct( CustomerRepository $customers, NoteRepository $notes, AuditLogRepository $audit_log, ActivityLogger $audit ) {
		$this->customers = $customers;
		$this->notes     = $notes;
		$this->audit_log = $audit_log;
		$this->audit     = $audit;
	}

	/**
	 * Fetch a customer with decoded tags and booking stats.
	 *
	 * @param int $id Customer id.
	 * @return array<string, mixed>|null
	 */
	public function get_with_relations( int $id ): ?array {
		$customer = $this->customers->find( $id );
		if ( null === $customer ) {
			return null;
		}
		$customer['tags']  = $this->decode_tags( $customer['tags'] ?? null );
		$customer['stats'] = $this->customers->booking_stats( $id );

		return $customer;
	}

	/**
	 * Create a customer.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $input ): array|WP_Error {
		$data = $this->validate( $input );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$id = $this->customers->create( $data );
		$this->audit->log(
			'customer.created',
			array(
				'entity_type' => 'customer',
				'entity_id'   => $id,
			)
		);

		return (array) $this->get_with_relations( $id );
	}

	/**
	 * Update a customer.
	 *
	 * @param int                  $id    Customer id.
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $id, array $input ): array|WP_Error {
		if ( null === $this->customers->find( $id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Customer not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		$data = $this->validate( $input, $id );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$this->customers->update( $id, $data );
		$this->audit->log(
			'customer.updated',
			array(
				'entity_type' => 'customer',
				'entity_id'   => $id,
			)
		);

		return (array) $this->get_with_relations( $id );
	}

	/**
	 * Soft-delete a customer.
	 *
	 * @param int $id Customer id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$ok = $this->customers->delete( $id );
		if ( $ok ) {
			$this->audit->log(
				'customer.deleted',
				array(
					'entity_type' => 'customer',
					'entity_id'   => $id,
				)
			);
		}

		return $ok;
	}

	/**
	 * Find an existing customer by email or phone, or create a new one.
	 *
	 * Used by the public booking flow to avoid duplicate records.
	 *
	 * @param array<string, mixed> $contact Contact details (name, email, phone).
	 * @return int|WP_Error Customer id, or a validation error.
	 */
	public function resolve_or_create( array $contact ): int|WP_Error {
		$email = isset( $contact['email'] ) ? sanitize_email( (string) $contact['email'] ) : '';
		if ( '' !== $email && is_email( $email ) ) {
			$existing = $this->customers->find_by( array( 'email' => $email ) );
			if ( null !== $existing ) {
				return (int) $existing['id'];
			}
		}

		$phone = isset( $contact['phone'] ) ? sanitize_text_field( (string) $contact['phone'] ) : '';
		if ( '' !== $phone ) {
			$existing = $this->customers->find_by( array( 'phone' => $phone ) );
			if ( null !== $existing ) {
				return (int) $existing['id'];
			}
		}

		$created = $this->create( $contact );
		if ( $created instanceof WP_Error ) {
			return $created;
		}

		return (int) $created['id'];
	}

	/**
	 * List a customer's notes.
	 *
	 * @param int $customer_id Customer id.
	 * @return array<int, array<string, mixed>>
	 */
	public function notes( int $customer_id ): array {
		return $this->notes->for_entity( 'customer', $customer_id );
	}

	/**
	 * Add a note to a customer.
	 *
	 * @param int    $customer_id Customer id.
	 * @param string $body        Note body.
	 * @param bool   $is_private  Whether the note is private.
	 * @return array<string, mixed>|WP_Error
	 */
	public function add_note( int $customer_id, string $body, bool $is_private = true ): array|WP_Error {
		$body = trim( wp_kses_post( $body ) );
		if ( '' === $body ) {
			return new WP_Error( 'bookora_validation', __( 'Note cannot be empty.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( null === $this->customers->find( $customer_id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Customer not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		$author_id = get_current_user_id();
		$id        = $this->notes->create(
			array(
				'entity_type' => 'customer',
				'entity_id'   => $customer_id,
				'author_id'   => $author_id > 0 ? $author_id : null,
				'body'        => $body,
				'is_private'  => $is_private ? 1 : 0,
			)
		);
		$this->audit->log(
			'customer.note_added',
			array(
				'entity_type' => 'customer',
				'entity_id'   => $customer_id,
			)
		);

		return (array) $this->notes->find( $id );
	}

	/**
	 * Delete a note belonging to a customer.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $note_id     Note id.
	 * @return bool
	 */
	public function delete_note( int $customer_id, int $note_id ): bool {
		$note = $this->notes->find( $note_id );
		if ( null === $note || 'customer' !== $note['entity_type'] || (int) $note['entity_id'] !== $customer_id ) {
			return false;
		}

		return $this->notes->delete( $note_id );
	}

	/**
	 * Booking history for a customer.
	 *
	 * @param int $customer_id Customer id.
	 * @return array<int, array<string, mixed>>
	 */
	public function bookings( int $customer_id ): array {
		return $this->customers->bookings_for_customer( $customer_id );
	}

	/**
	 * Assemble a merged activity timeline (notes + audit events), newest first.
	 *
	 * @param int $customer_id Customer id.
	 * @return array<int, array{type: string, at: string, summary: string, meta: array<string, mixed>}>
	 */
	public function timeline( int $customer_id ): array {
		$events = array();

		foreach ( $this->notes->for_entity( 'customer', $customer_id ) as $note ) {
			$events[] = array(
				'type'    => 'note',
				'at'      => (string) $note['created_at'],
				'summary' => wp_trim_words( wp_strip_all_tags( (string) $note['body'] ), 20 ),
				'meta'    => array( 'author_id' => (int) ( $note['author_id'] ?? 0 ) ),
			);
		}

		foreach ( $this->audit_log->for_entity( 'customer', $customer_id ) as $entry ) {
			$events[] = array(
				'type'    => 'activity',
				'at'      => (string) $entry['created_at'],
				'summary' => (string) $entry['action'],
				'meta'    => array( 'actor_id' => (int) ( $entry['actor_id'] ?? 0 ) ),
			);
		}

		usort( $events, static fn ( array $a, array $b ): int => strcmp( $b['at'], $a['at'] ) );

		return $events;
	}

	/**
	 * Distinct tags across customers.
	 *
	 * @return array<int, string>
	 */
	public function tags(): array {
		return $this->customers->distinct_tags();
	}

	/**
	 * Validate and sanitise customer input.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param int|null             $id    Existing id when updating.
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate( array $input, ?int $id = null ): array|WP_Error {
		$errors = array();
		$data   = array();

		if ( array_key_exists( 'first_name', $input ) ) {
			$data['first_name'] = sanitize_text_field( (string) $input['first_name'] );
		}
		if ( array_key_exists( 'last_name', $input ) ) {
			$data['last_name'] = sanitize_text_field( (string) $input['last_name'] );
		}

		$name = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
		if ( '' === $name ) {
			$name = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
		}
		if ( '' !== $name ) {
			$data['name'] = mb_substr( $name, 0, 191 );
		} elseif ( null === $id ) {
			$errors['name'] = __( 'A name is required.', 'bookora' );
		}

		if ( array_key_exists( 'email', $input ) ) {
			$email = '' === $input['email'] ? '' : sanitize_email( (string) $input['email'] );
			if ( '' !== (string) $input['email'] && ! is_email( $email ) ) {
				$errors['email'] = __( 'Enter a valid email address.', 'bookora' );
			} else {
				$data['email'] = $email;
			}
		}
		if ( array_key_exists( 'phone', $input ) ) {
			$data['phone'] = sanitize_text_field( (string) $input['phone'] );
		}
		if ( array_key_exists( 'timezone', $input ) ) {
			$data['timezone'] = sanitize_text_field( (string) $input['timezone'] );
		}
		if ( array_key_exists( 'locale', $input ) ) {
			$data['locale'] = sanitize_text_field( (string) $input['locale'] );
		}
		if ( array_key_exists( 'tags', $input ) ) {
			$data['tags'] = $this->encode_tags( $input['tags'] );
		}

		if ( '' !== ( $data['email'] ?? '' ) ) {
			$existing = $this->customers->find_by( array( 'email' => $data['email'] ) );
			if ( null !== $existing && (int) $existing['id'] !== (int) $id ) {
				$errors['email'] = __( 'A customer with this email already exists.', 'bookora' );
			}
		}

		if ( array() !== $errors ) {
			return new WP_Error(
				'bookora_validation',
				__( 'Please correct the highlighted fields.', 'bookora' ),
				array(
					'status' => 422,
					'fields' => $errors,
				)
			);
		}

		return $data;
	}

	/**
	 * Normalise tags (array or comma string) to a stored comma string.
	 *
	 * @param mixed $tags Input tags.
	 * @return string|null
	 */
	private function encode_tags( mixed $tags ): ?string {
		if ( is_string( $tags ) ) {
			$tags = explode( ',', $tags );
		}
		if ( ! is_array( $tags ) ) {
			return null;
		}
		$clean = array();
		foreach ( $tags as $tag ) {
			$tag = sanitize_text_field( trim( (string) $tag ) );
			if ( '' !== $tag ) {
				$clean[ $tag ] = true;
			}
		}
		$list = array_keys( $clean );

		return array() === $list ? null : mb_substr( implode( ', ', $list ), 0, 255 );
	}

	/**
	 * Decode a stored tags string into a list.
	 *
	 * @param string|null $tags Stored value.
	 * @return array<int, string>
	 */
	private function decode_tags( ?string $tags ): array {
		if ( null === $tags || '' === $tags ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $tags ) ) ) );
	}
}
