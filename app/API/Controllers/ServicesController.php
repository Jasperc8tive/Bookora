<?php
/**
 * Services REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Security\Capabilities;
use Bookora\Services\ServiceManager;
use Bookora\Services\ServiceRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD + search/filter/bulk endpoints for services.
 */
final class ServicesController extends AbstractController {

	/**
	 * Service manager.
	 *
	 * @var ServiceManager
	 */
	private ServiceManager $manager;

	/**
	 * Service repository.
	 *
	 * @var ServiceRepository
	 */
	private ServiceRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param ServiceManager    $manager    Service manager.
	 * @param ServiceRepository $repository Service repository.
	 */
	public function __construct( ServiceManager $manager, ServiceRepository $repository ) {
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
			'/services',
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
			'/services/bulk',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'bulk' ),
					'permission_callback' => $can,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/services/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $can,
				),
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
	 * List services.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$result = $this->repository->paginate(
			array(
				'search'          => (string) $request->get_param( 'search' ),
				'category_id'     => (int) $request->get_param( 'category_id' ),
				'status'          => (string) $request->get_param( 'status' ),
				'orderby'         => (string) $request->get_param( 'orderby' ),
				'order'           => (string) $request->get_param( 'order' ),
				'page'            => $page > 0 ? $page : 1,
				'per_page'        => $per_page > 0 ? $per_page : 20,
				'include_trashed' => (bool) $request->get_param( 'include_trashed' ),
			)
		);

		return $this->success( $result );
	}

	/**
	 * Create a service.
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
	 * Fetch a single service.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$service = $this->repository->find( (int) $request['id'] );
		if ( null === $service ) {
			return $this->error( 'bookora_not_found', __( 'Service not found.', 'bookora' ), 404 );
		}

		return $this->success( $service );
	}

	/**
	 * Update a service.
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
	 * Soft-delete a service.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$deleted = $this->manager->delete( (int) $request['id'] );

		return $this->success( array( 'deleted' => $deleted ) );
	}

	/**
	 * Apply a bulk action.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function bulk( WP_REST_Request $request ) {
		$params = (array) $request->get_json_params();
		$action = isset( $params['action'] ) ? (string) $params['action'] : '';
		$ids    = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : array();

		$result = $this->manager->bulk( $action, $ids );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( array( 'affected' => $result ) );
	}
}
