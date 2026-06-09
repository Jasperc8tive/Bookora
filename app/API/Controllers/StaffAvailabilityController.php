<?php
/**
 * Staff-availability REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Security\Capabilities;
use Bookora\Staff\AvailabilityManager;
use Bookora\Staff\StaffRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET / PUT a staff member's availability set.
 */
final class StaffAvailabilityController extends AbstractController {

	/**
	 * Availability manager.
	 *
	 * @var AvailabilityManager
	 */
	private AvailabilityManager $manager;

	/**
	 * Staff repository (existence checks).
	 *
	 * @var StaffRepository
	 */
	private StaffRepository $staff;

	/**
	 * Constructor.
	 *
	 * @param AvailabilityManager $manager Availability manager.
	 * @param StaffRepository     $staff   Staff repository.
	 */
	public function __construct( AvailabilityManager $manager, StaffRepository $staff ) {
		$this->manager = $manager;
		$this->staff   = $staff;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_STAFF );

		register_rest_route(
			self::REST_NAMESPACE,
			'/staff/(?P<id>\d+)/availability',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $can,
				),
			)
		);
	}

	/**
	 * Get availability for a staff member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$staff_id = (int) $request['id'];
		if ( null === $this->staff->find( $staff_id ) ) {
			return $this->error( 'bookora_not_found', __( 'Staff member not found.', 'bookora' ), 404 );
		}

		return $this->success( $this->manager->get( $staff_id ) );
	}

	/**
	 * Replace availability for a staff member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$staff_id = (int) $request['id'];
		if ( null === $this->staff->find( $staff_id ) ) {
			return $this->error( 'bookora_not_found', __( 'Staff member not found.', 'bookora' ), 404 );
		}

		$result = $this->manager->set( $staff_id, (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}
}
