<?php
/**
 * Availability REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes computed bookable slots for a service on a date.
 */
final class AvailabilityController extends AbstractController {

	/**
	 * Availability engine.
	 *
	 * @var AvailabilityEngine
	 */
	private AvailabilityEngine $engine;

	/**
	 * Constructor.
	 *
	 * @param AvailabilityEngine $engine Availability engine.
	 */
	public function __construct( AvailabilityEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => $this->require_capability( Capabilities::MANAGE_BOOKINGS ),
				'args'                => array(
					'service_id' => array( 'required' => true ),
					'date'       => array( 'required' => true ),
				),
			)
		);
	}

	/**
	 * Return slots for the requested service/date (and optional staff).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function index( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$date       = (string) $request->get_param( 'date' );
		$staff      = $request->get_param( 'staff_id' );
		$staff_id   = ( null !== $staff && '' !== $staff ) ? (int) $staff : null;

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $this->error( 'bookora_invalid_date', __( 'Date must be YYYY-MM-DD.', 'bookora' ), 422 );
		}

		return $this->success( $this->engine->for_date( $service_id, $staff_id, $date ) );
	}
}
