<?php
/**
 * Public (customer-facing) booking REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\BookingEngine;
use Bookora\Customers\CustomerManager;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Staff\StaffServiceRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Unauthenticated endpoints powering the front-end booking wizard.
 *
 * These are intentionally public (`__return_true`) — booking is open to site
 * visitors. They are defended by the global per-IP rate limiter, a honeypot
 * field, server-side validation, and server-authoritative pricing. They expose
 * only public-safe fields and never accept client-supplied prices or statuses.
 */
final class PublicBookingController extends AbstractController {

	private ServiceRepository $services;
	private StaffServiceRepository $assignments;
	private StaffRepository $staff;
	private AvailabilityEngine $availability;
	private BookingEngine $booking;
	private CustomerManager $customers;

	/**
	 * Constructor.
	 *
	 * @param ServiceRepository      $services     Service repository.
	 * @param StaffServiceRepository $assignments  Assignment repository.
	 * @param StaffRepository        $staff        Staff repository.
	 * @param AvailabilityEngine     $availability Availability engine.
	 * @param BookingEngine          $booking      Booking engine.
	 * @param CustomerManager        $customers    Customer manager.
	 */
	public function __construct(
		ServiceRepository $services,
		StaffServiceRepository $assignments,
		StaffRepository $staff,
		AvailabilityEngine $availability,
		BookingEngine $booking,
		CustomerManager $customers
	) {
		$this->services     = $services;
		$this->assignments  = $assignments;
		$this->staff        = $staff;
		$this->availability = $availability;
		$this->booking      = $booking;
		$this->customers    = $customers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$open = '__return_true';

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'services' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/services/(?P<id>\d+)/staff',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'staff' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/hold',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'hold' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/appointments',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => $open,
			)
		);
	}

	/**
	 * Public list of active services (public-safe fields only).
	 *
	 * @return WP_REST_Response
	 */
	public function services(): WP_REST_Response {
		$rows  = $this->services->all(
			array(
				'where'   => array( 'status' => 'active' ),
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);
		$items = array_map(
			static fn ( array $s ): array => array(
				'id'           => (int) $s['id'],
				'name'         => (string) $s['name'],
				'description'  => (string) ( $s['description'] ?? '' ),
				'duration_min' => (int) $s['duration_min'],
				'price'        => (float) $s['price'],
				'currency'     => (string) $s['currency'],
				'category_id'  => null !== $s['category_id'] ? (int) $s['category_id'] : null,
			),
			$rows
		);

		return $this->success( array( 'items' => $items ) );
	}

	/**
	 * Staff who offer a service (public-safe fields).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function staff( WP_REST_Request $request ): WP_REST_Response {
		$items = array();
		foreach ( $this->assignments->staff_ids_for_service( (int) $request['id'] ) as $staff_id ) {
			$member = $this->staff->find( $staff_id );
			if ( null !== $member ) {
				$items[] = array(
					'id'   => (int) $member['id'],
					'name' => (string) $member['display_name'],
				);
			}
		}

		return $this->success( array( 'items' => $items ) );
	}

	/**
	 * Public availability for a service/date (and optional staff).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function availability( WP_REST_Request $request ) {
		$date = (string) $request->get_param( 'date' );
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $this->error( 'bookora_invalid_date', __( 'Date must be YYYY-MM-DD.', 'bookora' ), 422 );
		}
		$staff = $request->get_param( 'staff_id' );

		return $this->success(
			$this->availability->for_date(
				(int) $request->get_param( 'service_id' ),
				( null !== $staff && '' !== $staff ) ? (int) $staff : null,
				$date
			)
		);
	}

	/**
	 * Place a hold on a slot during checkout.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function hold( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$result = $this->booking->hold(
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
	 * Create a booking from the public wizard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();

		// Honeypot: real users never fill this hidden field.
		if ( '' !== trim( (string) ( $body['hp'] ?? '' ) ) ) {
			return $this->error( 'bookora_rejected', __( 'Submission rejected.', 'bookora' ), 422 );
		}

		$contact     = (array) ( $body['customer'] ?? array() );
		$customer_id = $this->customers->resolve_or_create( $contact );
		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$result = $this->booking->create(
			array(
				'service_id'    => (int) ( $body['service_id'] ?? 0 ),
				'staff_id'      => (int) ( $body['staff_id'] ?? 0 ),
				'customer_id'   => $customer_id,
				'start'         => (string) ( $body['start'] ?? '' ),
				'session_token' => (string) ( $body['session_token'] ?? '' ),
				'status'        => 'pending',
				'source'        => 'online',
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$appt = $result['created'][0];

		return $this->success(
			array(
				'appointment' => array(
					'id'         => (int) $appt['id'],
					'service_id' => (int) $appt['service_id'],
					'staff_id'   => (int) $appt['staff_id'],
					'start_at'   => (string) $appt['start_at'],
					'end_at'     => (string) $appt['end_at'],
					'status'     => (string) $appt['status'],
					'total'      => (string) $appt['total'],
					'currency'   => (string) $appt['currency'],
				),
			),
			201
		);
	}
}
