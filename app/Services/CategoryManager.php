<?php
/**
 * Service-category application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Services;

use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, sanitises, and persists service categories.
 */
final class CategoryManager {

	private const STATUSES = array( 'active', 'inactive' );

	/**
	 * Category repository.
	 *
	 * @var CategoryRepository
	 */
	private CategoryRepository $categories;

	/**
	 * Service repository (for reference checks).
	 *
	 * @var ServiceRepository
	 */
	private ServiceRepository $services;

	/**
	 * Audit logger.
	 *
	 * @var ActivityLogger
	 */
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param CategoryRepository $categories Category repository.
	 * @param ServiceRepository  $services   Service repository.
	 * @param ActivityLogger     $audit      Audit logger.
	 */
	public function __construct( CategoryRepository $categories, ServiceRepository $services, ActivityLogger $audit ) {
		$this->categories = $categories;
		$this->services   = $services;
		$this->audit      = $audit;
	}

	/**
	 * Create a category.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $input ): array|WP_Error {
		$data = $this->validate( $input );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$id = $this->categories->create( $data );
		$this->audit->log(
			'service_category.created',
			array(
				'entity_type' => 'service_category',
				'entity_id'   => $id,
			)
		);

		return (array) $this->categories->find( $id );
	}

	/**
	 * Update a category.
	 *
	 * @param int                  $id    Category id.
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $id, array $input ): array|WP_Error {
		if ( null === $this->categories->find( $id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Category not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		$data = $this->validate( $input, $id );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$this->categories->update( $id, $data );
		$this->audit->log(
			'service_category.updated',
			array(
				'entity_type' => 'service_category',
				'entity_id'   => $id,
			)
		);

		return (array) $this->categories->find( $id );
	}

	/**
	 * Soft-delete a category.
	 *
	 * @param int $id Category id.
	 * @return array{deleted: bool, services_affected: int}
	 */
	public function delete( int $id ): array {
		$affected = $this->services->count_in_category( $id );
		$deleted  = $this->categories->delete( $id );
		if ( $deleted ) {
			$this->audit->log(
				'service_category.deleted',
				array(
					'entity_type' => 'service_category',
					'entity_id'   => $id,
				)
			);
		}

		return array(
			'deleted'           => $deleted,
			'services_affected' => $affected,
		);
	}

	/**
	 * Validate and sanitise input.
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

		if ( array_key_exists( 'description', $input ) ) {
			$data['description'] = wp_kses_post( (string) $input['description'] );
		}

		if ( array_key_exists( 'color', $input ) ) {
			$color         = sanitize_hex_color( (string) $input['color'] );
			$data['color'] = $color ? $color : null;
		}

		if ( array_key_exists( 'sort_order', $input ) ) {
			$data['sort_order'] = (int) $input['sort_order'];
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
}
