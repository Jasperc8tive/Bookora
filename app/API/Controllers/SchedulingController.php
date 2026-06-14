<?php
/**
 * AI scheduling REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Scheduling\SchedulingIntelligence;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Smart-scheduling endpoints: slot suggestions, auto-assignment, forecasting,
 * and workload balance.
 */
final class SchedulingController extends AbstractController {

	private SchedulingIntelligence $ai;

	/**
	 * Constructor.
	 *
	 * @param SchedulingIntelligence $ai Scheduling intelligence.
	 */
	public function __construct( SchedulingIntelligence $ai ) {
		$this->ai = $ai;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_BOOKINGS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/scheduling/suggestions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'suggestions' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scheduling/auto-assign',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'auto_assign' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scheduling/forecast',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'forecast' ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/scheduling/workload',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'workload' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * Ranked slot suggestions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function suggestions( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$from       = (string) $request->get_param( 'from' );
		$to         = (string) $request->get_param( 'to' );
		if ( $service_id <= 0 || ! $this->valid_date( $from ) || ! $this->valid_date( $to ) ) {
			return $this->error( 'bookora_invalid', __( 'service_id, from and to (YYYY-MM-DD) are required.', 'bookora' ), 422 );
		}
		$prefer = (string) $request->get_param( 'prefer' );
		$limit  = (int) $request->get_param( 'limit' );

		return $this->success(
			array(
				'items' => $this->ai->suggest( $service_id, $from, $to, in_array( $prefer, array( 'morning', 'afternoon', 'evening' ), true ) ? $prefer : '', $limit > 0 ? $limit : 5 ),
			)
		);
	}

	/**
	 * Suggest the least-loaded staff for a slot.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function auto_assign( WP_REST_Request $request ) {
		$service_id = (int) $request->get_param( 'service_id' );
		$start      = (string) $request->get_param( 'start' );
		if ( $service_id <= 0 || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $start ) ) {
			return $this->error( 'bookora_invalid', __( 'service_id and a start datetime are required.', 'bookora' ), 422 );
		}

		return $this->success( array( 'staff_id' => $this->ai->auto_assign( $service_id, $start ) ) );
	}

	/**
	 * Demand forecast.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function forecast( WP_REST_Request $request ): WP_REST_Response {
		$days = (int) $request->get_param( 'days' );

		return $this->success( $this->ai->forecast( $days > 0 ? $days : 14 ) );
	}

	/**
	 * Per-staff workload balance.
	 *
	 * @return WP_REST_Response
	 */
	public function workload(): WP_REST_Response {
		return $this->success( array( 'items' => $this->ai->workload() ) );
	}

	/**
	 * Whether a string is a YYYY-MM-DD date.
	 *
	 * @param string $date Candidate.
	 * @return bool
	 */
	private function valid_date( string $date ): bool {
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date );
	}
}
