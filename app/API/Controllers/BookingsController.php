<?php
/**
 * Bookings REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\BookingEngine;
use Bookora\Appointments\Clock;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Admin CRUD + lifecycle endpoints for appointments (manual booking, list,
 * reschedule, cancel, hold). Public/customer booking arrives in Stage 7.
 */
final class BookingsController extends AbstractController {

	private BookingEngine $engine;
	private AppointmentRepository $repository;
	private Clock $clock;

	/**
	 * Constructor.
	 *
	 * @param BookingEngine         $engine     Booking engine.
	 * @param AppointmentRepository $repository Appointment repository.
	 * @param Clock                 $clock      Clock.
	 */
	public function __construct( BookingEngine $engine, AppointmentRepository $repository, Clock $clock ) {
		$this->engine     = $engine;
		$this->repository = $repository;
		$this->clock      = $clock;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_BOOKINGS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/bookings',
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
			'/bookings/hold',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'hold' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/bookings/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'reschedule' ),
					'permission_callback' => $can,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * List appointments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'staff_id'    => (int) $request->get_param( 'staff_id' ),
			'customer_id' => (int) $request->get_param( 'customer_id' ),
			'status'      => (string) $request->get_param( 'status' ),
			'page'        => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page'    => (int) $request->get_param( 'per_page' ) > 0 ? (int) $request->get_param( 'per_page' ) : 20,
		);

		$from = (string) $request->get_param( 'from' );
		$to   = (string) $request->get_param( 'to' );
		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$args['from'] = $this->clock->day_bounds_utc( $from )[0];
		}
		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$args['to'] = $this->clock->day_bounds_utc( $to )[1];
		}

		return $this->success( $this->repository->paginate( $args ) );
	}

	/**
	 * Create a booking (single or recurring).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$result = $this->engine->create( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result, 201 );
	}

	/**
	 * Place a hold on a slot.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function hold( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$result = $this->engine->hold(
			(int) ( $body['service_id'] ?? 0 ),
			(int) ( $body['staff_id'] ?? 0 ),
			(string) ( $body['start'] ?? '' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result, 201 );
	}

	/**
	 * Fetch one appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$appt = $this->repository->find( (int) $request['id'] );
		if ( null === $appt ) {
			return $this->error( 'bookora_not_found', __( 'Appointment not found.', 'bookora' ), 404 );
		}

		return $this->success( $appt );
	}

	/**
	 * Reschedule an appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function reschedule( WP_REST_Request $request ) {
		$result = $this->engine->reschedule( (int) $request['id'], (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}

	/**
	 * Cancel an appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function cancel( WP_REST_Request $request ): WP_REST_Response {
		$body = (array) $request->get_json_params();

		return $this->success(
			array( 'cancelled' => $this->engine->cancel( (int) $request['id'], (string) ( $body['reason'] ?? '' ) ) )
		);
	}
}
