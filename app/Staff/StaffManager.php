<?php
/**
 * Staff application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Staff;

use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, sanitises, and persists staff profiles, including assigned
 * services and skills, emitting audit events.
 */
final class StaffManager {

	private const STATUSES = array( 'active', 'inactive' );

	/**
	 * Staff repository.
	 *
	 * @var StaffRepository
	 */
	private StaffRepository $staff;

	/**
	 * Assigned-services repository.
	 *
	 * @var StaffServiceRepository
	 */
	private StaffServiceRepository $assignments;

	/**
	 * Audit logger.
	 *
	 * @var ActivityLogger
	 */
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param StaffRepository        $staff       Staff repository.
	 * @param StaffServiceRepository $assignments Assigned-services repository.
	 * @param ActivityLogger         $audit       Audit logger.
	 */
	public function __construct( StaffRepository $staff, StaffServiceRepository $assignments, ActivityLogger $audit ) {
		$this->staff       = $staff;
		$this->assignments = $assignments;
		$this->audit       = $audit;
	}

	/**
	 * Fetch a staff member with assigned service ids and decoded skills.
	 *
	 * @param int $id Staff id.
	 * @return array<string, mixed>|null
	 */
	public function get_with_relations( int $id ): ?array {
		$staff = $this->staff->find( $id );
		if ( null === $staff ) {
			return null;
		}
		$staff['service_ids'] = $this->assignments->service_ids_for_staff( $id );
		$staff['skills']      = $this->decode_skills( $staff['skills'] ?? null );

		return $staff;
	}

	/**
	 * Create a staff member.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $input ): array|WP_Error {
		$data = $this->validate( $input );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$id = $this->staff->create( $data );
		$this->sync_relations( $id, $input );
		$this->audit->log(
			'staff.created',
			array(
				'entity_type' => 'staff',
				'entity_id'   => $id,
			)
		);

		return (array) $this->get_with_relations( $id );
	}

	/**
	 * Update a staff member.
	 *
	 * @param int                  $id    Staff id.
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $id, array $input ): array|WP_Error {
		if ( null === $this->staff->find( $id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Staff member not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		$data = $this->validate( $input, $id );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$this->staff->update( $id, $data );
		$this->sync_relations( $id, $input );
		$this->audit->log(
			'staff.updated',
			array(
				'entity_type' => 'staff',
				'entity_id'   => $id,
			)
		);

		return (array) $this->get_with_relations( $id );
	}

	/**
	 * Soft-delete a staff member.
	 *
	 * @param int $id Staff id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$ok = $this->staff->delete( $id );
		if ( $ok ) {
			$this->audit->log(
				'staff.deleted',
				array(
					'entity_type' => 'staff',
					'entity_id'   => $id,
				)
			);
		}

		return $ok;
	}

	/**
	 * Persist assigned services and skills from input.
	 *
	 * @param int                  $id    Staff id.
	 * @param array<string, mixed> $input Raw input.
	 * @return void
	 */
	private function sync_relations( int $id, array $input ): void {
		if ( array_key_exists( 'service_ids', $input ) && is_array( $input['service_ids'] ) ) {
			$this->assignments->sync_for_staff( $id, $input['service_ids'] );
		}
	}

	/**
	 * Validate and sanitise input into a persistable row.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param int|null             $id    Existing id when updating.
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate( array $input, ?int $id = null ): array|WP_Error {
		$errors = array();
		$data   = array();

		$name = isset( $input['display_name'] ) ? sanitize_text_field( (string) $input['display_name'] ) : '';
		if ( '' === $name && null === $id ) {
			$errors['display_name'] = __( 'Display name is required.', 'bookora' );
		}
		if ( '' !== $name ) {
			$data['display_name'] = mb_substr( $name, 0, 191 );
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
		if ( array_key_exists( 'bio', $input ) ) {
			$data['bio'] = wp_kses_post( (string) $input['bio'] );
		}
		if ( array_key_exists( 'avatar_url', $input ) ) {
			$data['avatar_url'] = '' === $input['avatar_url'] ? null : esc_url_raw( (string) $input['avatar_url'] );
		}
		if ( array_key_exists( 'timezone', $input ) ) {
			$data['timezone'] = sanitize_text_field( (string) $input['timezone'] );
		}
		if ( array_key_exists( 'color', $input ) ) {
			$color         = sanitize_hex_color( (string) $input['color'] );
			$data['color'] = $color ? $color : null;
		}
		if ( array_key_exists( 'wp_user_id', $input ) ) {
			$data['wp_user_id'] = (int) $input['wp_user_id'] > 0 ? (int) $input['wp_user_id'] : null;
		}

		if ( array_key_exists( 'skills', $input ) ) {
			$data['skills'] = $this->encode_skills( $input['skills'] );
		}

		if ( array_key_exists( 'status', $input ) ) {
			$status = (string) $input['status'];
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				$errors['status'] = __( 'Invalid status.', 'bookora' );
			} else {
				$data['status'] = $status;
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
	 * Encode a skills list (array or comma string) to stored JSON.
	 *
	 * @param mixed $skills Input skills.
	 * @return string|null
	 */
	private function encode_skills( mixed $skills ): ?string {
		if ( is_string( $skills ) ) {
			$skills = array_filter( array_map( 'trim', explode( ',', $skills ) ) );
		}
		if ( ! is_array( $skills ) ) {
			return null;
		}
		$clean = array_values( array_filter( array_map( static fn ( $s ): string => sanitize_text_field( (string) $s ), $skills ) ) );

		return array() === $clean ? null : (string) wp_json_encode( $clean );
	}

	/**
	 * Decode stored skills JSON to a list.
	 *
	 * @param string|null $skills Stored value.
	 * @return array<int, string>
	 */
	private function decode_skills( ?string $skills ): array {
		if ( null === $skills || '' === $skills ) {
			return array();
		}
		$decoded = json_decode( $skills, true );

		return is_array( $decoded ) ? array_map( 'strval', $decoded ) : array();
	}
}
