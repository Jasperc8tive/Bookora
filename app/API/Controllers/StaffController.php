<?php
/**
 * Staff REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Security\Capabilities;
use Bookora\Staff\StaffManager;
use Bookora\Staff\StaffRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD + search/filter endpoints for staff, including assigned services & skills.
 */
final class StaffController extends AbstractController {

	/**
	 * Staff manager.
	 *
	 * @var StaffManager
	 */
	private StaffManager $manager;

	/**
	 * Staff repository.
	 *
	 * @var StaffRepository
	 */
	private StaffRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param StaffManager    $manager    Staff manager.
	 * @param StaffRepository $repository Staff repository.
	 */
	public function __construct( StaffManager $manager, StaffRepository $repository ) {
		$this->manager    = $manager;
		$this->repository = $repository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_STAFF );

		register_rest_route(
			self::REST_NAMESPACE,
			'/staff',
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
			'/staff/(?P<id>\d+)',
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
	 * List staff.
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
					'status'   => (string) $request->get_param( 'status' ),
					'orderby'  => (string) $request->get_param( 'orderby' ),
					'order'    => (string) $request->get_param( 'order' ),
					'page'     => $page > 0 ? $page : 1,
					'per_page' => $per_page > 0 ? $per_page : 20,
				)
			)
		);
	}

	/**
	 * Create a staff member.
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
	 * Fetch a staff member with relations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$staff = $this->manager->get_with_relations( (int) $request['id'] );
		if ( null === $staff ) {
			return $this->error( 'bookora_not_found', __( 'Staff member not found.', 'bookora' ), 404 );
		}

		return $this->success( $staff );
	}

	/**
	 * Update a staff member.
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
	 * Soft-delete a staff member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'deleted' => $this->manager->delete( (int) $request['id'] ) ) );
	}
}
