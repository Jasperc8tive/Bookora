<?php
/**
 * Resource (rooms / equipment) application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Resources;

use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Manages bookable resources and checks resource availability.
 */
final class ResourceManager {

	private ResourceRepository $resources;
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param ResourceRepository $resources Resource repository.
	 * @param ActivityLogger     $audit     Audit logger.
	 */
	public function __construct( ResourceRepository $resources, ActivityLogger $audit ) {
		$this->resources = $resources;
		$this->audit     = $audit;
	}

	/**
	 * List active resources.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function listing(): array {
		return $this->resources->all(
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);
	}

	/**
	 * Create a resource.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $input ): array|WP_Error {
		$data = $this->validate( $input, true );
		if ( $data instanceof WP_Error ) {
			return $data;
		}
		$id = $this->resources->create( $data );
		$this->audit->log(
			'resource.created',
			array(
				'entity_type' => 'resource',
				'entity_id'   => $id,
			)
		);

		return (array) $this->resources->find( $id );
	}

	/**
	 * Update a resource.
	 *
	 * @param int                  $id    Resource id.
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $id, array $input ): array|WP_Error {
		if ( null === $this->resources->find( $id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Resource not found.', 'bookora' ), array( 'status' => 404 ) );
		}
		$data = $this->validate( $input, false );
		if ( $data instanceof WP_Error ) {
			return $data;
		}
		$this->resources->update( $id, $data );

		return (array) $this->resources->find( $id );
	}

	/**
	 * Soft-delete a resource.
	 *
	 * @param int $id Resource id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->resources->delete( $id );
	}

	/**
	 * Whether a resource has spare capacity for a UTC window.
	 *
	 * @param int      $resource_id Resource id.
	 * @param string   $start_utc   Window start (UTC).
	 * @param string   $end_utc     Window end (UTC).
	 * @param int|null $ignore_id   Appointment id to exclude.
	 * @return bool
	 */
	public function is_free( int $resource_id, string $start_utc, string $end_utc, ?int $ignore_id = null ): bool {
		$resource = $this->resources->find( $resource_id );
		if ( null === $resource || 'active' !== $resource['status'] ) {
			return false;
		}
		$capacity = max( 1, (int) $resource['capacity'] );

		return $this->resources->overlapping( $resource_id, $start_utc, $end_utc, $ignore_id ) < $capacity;
	}

	/**
	 * Validate + sanitise resource input.
	 *
	 * @param array<string, mixed> $input    Raw input.
	 * @param bool                 $creating Whether creating.
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate( array $input, bool $creating ): array|WP_Error {
		$data = array();
		if ( array_key_exists( 'name', $input ) ) {
			$data['name'] = sanitize_text_field( (string) $input['name'] );
		}
		if ( $creating && empty( $data['name'] ) ) {
			return new WP_Error( 'bookora_validation', __( 'A resource name is required.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( array_key_exists( 'type', $input ) ) {
			$data['type'] = sanitize_text_field( (string) $input['type'] );
		}
		if ( array_key_exists( 'capacity', $input ) ) {
			$data['capacity'] = max( 1, (int) $input['capacity'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$data['description'] = wp_kses_post( (string) $input['description'] );
		}
		if ( array_key_exists( 'status', $input ) ) {
			$data['status'] = in_array( $input['status'], array( 'active', 'inactive' ), true ) ? (string) $input['status'] : 'active';
		}

		return $data;
	}
}
