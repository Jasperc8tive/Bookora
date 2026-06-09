<?php
/**
 * Service-categories REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Security\Capabilities;
use Bookora\Services\CategoryManager;
use Bookora\Services\CategoryRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD endpoints for service categories.
 */
final class ServiceCategoriesController extends AbstractController {

	/**
	 * Category manager.
	 *
	 * @var CategoryManager
	 */
	private CategoryManager $manager;

	/**
	 * Category repository.
	 *
	 * @var CategoryRepository
	 */
	private CategoryRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param CategoryManager    $manager    Category manager.
	 * @param CategoryRepository $repository Category repository.
	 */
	public function __construct( CategoryManager $manager, CategoryRepository $repository ) {
		$this->manager    = $manager;
		$this->repository = $repository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_SERVICES );

		register_rest_route(
			self::REST_NAMESPACE,
			'/service-categories',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $can,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/service-categories/(?P<id>\d+)',
			array(
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => $can,
				),
			)
		);
	}

	/**
	 * List categories (ordered).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$include_trashed = (bool) $request->get_param( 'include_trashed' );

		return $this->success( array( 'items' => $this->repository->list_ordered( $include_trashed ) ) );
	}

	/**
	 * Create a category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$result = $this->manager->create( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result, 201 );
	}

	/**
	 * Update a category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$result = $this->manager->update( (int) $request['id'], (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}

	/**
	 * Soft-delete a category.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( $this->manager->delete( (int) $request['id'] ) );
	}
}
