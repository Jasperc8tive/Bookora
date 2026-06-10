<?php
/**
 * Customers REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Customers\CustomerManager;
use Bookora\Customers\CustomerRepository;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD + list + tags + booking-history + timeline endpoints for customers.
 */
final class CustomersController extends AbstractController {

	/**
	 * Customer manager.
	 *
	 * @var CustomerManager
	 */
	private CustomerManager $manager;

	/**
	 * Customer repository.
	 *
	 * @var CustomerRepository
	 */
	private CustomerRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param CustomerManager    $manager    Customer manager.
	 * @param CustomerRepository $repository Customer repository.
	 */
	public function __construct( CustomerManager $manager, CustomerRepository $repository ) {
		$this->manager    = $manager;
		$this->repository = $repository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_CUSTOMERS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers',
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
			'/customers/tags',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'tags' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers/(?P<id>\d+)',
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers/(?P<id>\d+)/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'bookings' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers/(?P<id>\d+)/timeline',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'timeline' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * List customers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		return $this->success(
			$this->repository->paginate(
				array(
					'search'   => (string) $request->get_param( 'search' ),
					'tag'      => (string) $request->get_param( 'tag' ),
					'orderby'  => (string) $request->get_param( 'orderby' ),
					'order'    => (string) $request->get_param( 'order' ),
					'page'     => $page > 0 ? $page : 1,
					'per_page' => $per_page > 0 ? $per_page : 20,
				)
			)
		);
	}

	/**
	 * Distinct tags.
	 *
	 * @return WP_REST_Response
	 */
	public function tags(): WP_REST_Response {
		return $this->success( array( 'tags' => $this->manager->tags() ) );
	}

	/**
	 * Create a customer.
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
	 * Fetch a customer with relations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$customer = $this->manager->get_with_relations( (int) $request['id'] );
		if ( null === $customer ) {
			return $this->error( 'bookora_not_found', __( 'Customer not found.', 'bookora' ), 404 );
		}

		return $this->success( $customer );
	}

	/**
	 * Update a customer.
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
	 * Soft-delete a customer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'deleted' => $this->manager->delete( (int) $request['id'] ) ) );
	}

	/**
	 * Booking history for a customer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function bookings( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'items' => $this->manager->bookings( (int) $request['id'] ) ) );
	}

	/**
	 * Activity timeline for a customer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function timeline( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'items' => $this->manager->timeline( (int) $request['id'] ) ) );
	}
}
