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
			'/bookings/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'calendar' ),
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
	 * Calendar feed: appointments in a date range as FullCalendar events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function calendar( WP_REST_Request $request ) {
		$from = (string) $request->get_param( 'from' );
		$to   = (string) $request->get_param( 'to' );
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			return $this->error( 'bookora_invalid_range', __( 'from and to must be YYYY-MM-DD.', 'bookora' ), 422 );
		}

		$rows   = $this->repository->calendar(
			$this->clock->day_bounds_utc( $from )[0],
			$this->clock->day_bounds_utc( $to )[0],
			array(
				'staff_id'   => (int) $request->get_param( 'staff_id' ),
				'service_id' => (int) $request->get_param( 'service_id' ),
				'status'     => (string) $request->get_param( 'status' ),
			)
		);
		$events = array_map( array( $this, 'to_event' ), $rows );

		return $this->success( array( 'events' => $events ) );
	}

	/**
	 * Map an appointment row to a FullCalendar event (times in site-local ISO).
	 *
	 * @param array<string, mixed> $row Appointment row.
	 * @return array<string, mixed>
	 */
	private function to_event( array $row ): array {
		$status   = (string) $row['status'];
		$customer = (string) ( $row['customer_name'] ?? __( 'Customer', 'bookora' ) );
		$service  = (string) ( $row['service_name'] ?? __( 'Service', 'bookora' ) );
		$color    = '' !== (string) ( $row['staff_color'] ?? '' ) ? (string) $row['staff_color'] : $this->status_color( $status );

		return array(
			'id'              => (string) $row['id'],
			'title'           => $service . ' — ' . $customer,
			'start'           => str_replace( ' ', 'T', $this->clock->local_string( $this->clock->utc_to_epoch( (string) $row['start_at'] ) ) ),
			'end'             => str_replace( ' ', 'T', $this->clock->local_string( $this->clock->utc_to_epoch( (string) $row['end_at'] ) ) ),
			'backgroundColor' => $color,
			'borderColor'     => $color,
			'extendedProps'   => array(
				'status'       => $status,
				'staff_id'     => (int) $row['staff_id'],
				'staff_name'   => (string) ( $row['staff_name'] ?? '' ),
				'service_id'   => (int) $row['service_id'],
				'customer_id'  => (int) $row['customer_id'],
				'customerName' => $customer,
			),
		);
	}

	/**
	 * Default event colour by status.
	 *
	 * @param string $status Appointment status.
	 * @return string
	 */
	private function status_color( string $status ): string {
		return match ( $status ) {
			'confirmed' => '#0d9488',
			'completed' => '#16a34a',
			'cancelled' => '#9ca3af',
			'no_show'   => '#dc2626',
			default     => '#d97706',
		};
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
