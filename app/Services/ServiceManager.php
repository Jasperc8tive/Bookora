<?php
/**
 * Service application service (validation + persistence orchestration).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Services;

use Bookora\Core\Settings;
use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, sanitises, and persists services, emitting audit events.
 */
final class ServiceManager {

	private const STATUSES      = array( 'active', 'inactive' );
	private const DEPOSIT_TYPES = array( 'none', 'fixed', 'percent' );

	/**
	 * Service repository.
	 *
	 * @var ServiceRepository
	 */
	private ServiceRepository $services;

	/**
	 * Category repository.
	 *
	 * @var CategoryRepository
	 */
	private CategoryRepository $categories;

	/**
	 * Audit logger.
	 *
	 * @var ActivityLogger
	 */
	private ActivityLogger $audit;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param ServiceRepository  $services   Service repository.
	 * @param CategoryRepository $categories Category repository.
	 * @param ActivityLogger     $audit      Audit logger.
	 * @param Settings           $settings   Settings.
	 */
	public function __construct( ServiceRepository $services, CategoryRepository $categories, ActivityLogger $audit, Settings $settings ) {
		$this->services   = $services;
		$this->categories = $categories;
		$this->audit      = $audit;
		$this->settings   = $settings;
	}

	/**
	 * Create a service.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error The created row or a validation error.
	 */
	public function create( array $input ): array|WP_Error {
		$data = $this->validate( $input );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$id = $this->services->create( $data );
		$this->audit->log(
			'service.created',
			array(
				'entity_type' => 'service',
				'entity_id'   => $id,
			)
		);

		return (array) $this->services->find( $id );
	}

	/**
	 * Update a service.
	 *
	 * @param int                  $id    Service id.
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error The updated row or an error.
	 */
	public function update( int $id, array $input ): array|WP_Error {
		if ( null === $this->services->find( $id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Service not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		$data = $this->validate( $input, $id );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$this->services->update( $id, $data );
		$this->audit->log(
			'service.updated',
			array(
				'entity_type' => 'service',
				'entity_id'   => $id,
			)
		);

		return (array) $this->services->find( $id );
	}

	/**
	 * Soft-delete a service.
	 *
	 * @param int $id Service id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$ok = $this->services->delete( $id );
		if ( $ok ) {
			$this->audit->log(
				'service.deleted',
				array(
					'entity_type' => 'service',
					'entity_id'   => $id,
				)
			);
		}

		return $ok;
	}

	/**
	 * Apply a bulk action to a set of service ids.
	 *
	 * @param string         $action One of delete|restore|activate|deactivate.
	 * @param array<int,int> $ids    Service ids.
	 * @return int|WP_Error Number affected, or an error for an unknown action.
	 */
	public function bulk( string $action, array $ids ): int|WP_Error {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return 0;
		}

		$affected = 0;
		foreach ( $ids as $id ) {
			$done = match ( $action ) {
				'delete'     => $this->services->delete( $id ),
				'restore'    => $this->services->restore( $id ),
				'activate'   => $this->services->update( $id, array( 'status' => 'active' ) ),
				'deactivate' => $this->services->update( $id, array( 'status' => 'inactive' ) ),
				default      => null,
			};

			if ( null === $done ) {
				return new WP_Error( 'bookora_bad_action', __( 'Unknown bulk action.', 'bookora' ), array( 'status' => 400 ) );
			}
			$affected += $done ? 1 : 0;
		}

		$this->audit->log(
			'service.bulk',
			array(
				'entity_type' => 'service',
				'context'     => array(
					'action' => $action,
					'count'  => $affected,
				),
			)
		);

		return $affected;
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

		$name = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
		if ( '' === $name && null === $id ) {
			$errors['name'] = __( 'Name is required.', 'bookora' );
		}
		if ( '' !== $name ) {
			$data['name'] = mb_substr( $name, 0, 191 );
			$data['slug'] = ! empty( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $name );
		}

		if ( array_key_exists( 'duration_min', $input ) ) {
			$duration = (int) $input['duration_min'];
			if ( $duration < 1 ) {
				$errors['duration_min'] = __( 'Duration must be at least 1 minute.', 'bookora' );
			} else {
				$data['duration_min'] = $duration;
			}
		} elseif ( null === $id ) {
			$data['duration_min'] = 30;
		}

		foreach ( array( 'buffer_before_min', 'buffer_after_min', 'min_notice_min', 'lead_time_min' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = max( 0, (int) $input[ $field ] );
			}
		}

		if ( array_key_exists( 'max_notice_min', $input ) ) {
			$data['max_notice_min'] = '' === $input['max_notice_min'] || null === $input['max_notice_min'] ? null : max( 0, (int) $input['max_notice_min'] );
		}

		if ( array_key_exists( 'capacity', $input ) ) {
			$data['capacity'] = max( 1, (int) $input['capacity'] );
		}

		if ( array_key_exists( 'price', $input ) ) {
			$price = (float) $input['price'];
			if ( $price < 0 ) {
				$errors['price'] = __( 'Price cannot be negative.', 'bookora' );
			} else {
				$data['price'] = round( $price, 2 );
			}
		}

		$data['currency'] = ! empty( $input['currency'] )
			? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $input['currency'] ), 0, 3 ) )
			: (string) $this->settings->get( 'currency', 'NGN' );

		if ( array_key_exists( 'deposit_type', $input ) ) {
			$type = (string) $input['deposit_type'];
			if ( ! in_array( $type, self::DEPOSIT_TYPES, true ) ) {
				$errors['deposit_type'] = __( 'Invalid deposit type.', 'bookora' );
			} else {
				$data['deposit_type'] = $type;
			}
		}

		if ( array_key_exists( 'deposit_value', $input ) ) {
			$value = max( 0, (float) $input['deposit_value'] );
			if ( ( $data['deposit_type'] ?? '' ) === 'percent' && $value > 100 ) {
				$errors['deposit_value'] = __( 'Percentage deposit cannot exceed 100.', 'bookora' );
			} else {
				$data['deposit_value'] = round( $value, 2 );
			}
		}

		if ( array_key_exists( 'category_id', $input ) ) {
			$category_id = (int) $input['category_id'];
			if ( $category_id > 0 && null === $this->categories->find( $category_id ) ) {
				$errors['category_id'] = __( 'Selected category does not exist.', 'bookora' );
			} else {
				$data['category_id'] = $category_id > 0 ? $category_id : null;
			}
		}

		if ( array_key_exists( 'status', $input ) ) {
			$status = (string) $input['status'];
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				$errors['status'] = __( 'Invalid status.', 'bookora' );
			} else {
				$data['status'] = $status;
			}
		}

		if ( array_key_exists( 'image_url', $input ) ) {
			$data['image_url'] = '' === $input['image_url'] ? null : esc_url_raw( (string) $input['image_url'] );
		}

		if ( array_key_exists( 'description', $input ) ) {
			$data['description'] = wp_kses_post( (string) $input['description'] );
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
}
