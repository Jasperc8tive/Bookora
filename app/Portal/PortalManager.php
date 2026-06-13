<?php
/**
 * Customer portal application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Portal;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\BookingEngine;
use Bookora\Appointments\Clock;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerManager;
use Bookora\Customers\CustomerRepository;
use Bookora\Payments\PaymentManager;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Powers the self-service customer portal: profile, bookings, reschedule, cancel,
 * invoices, and magic-link issuance — all scoped to the authenticated customer
 * and enforcing the configured reschedule/cancel windows.
 */
final class PortalManager {

	private CustomerRepository $customers;
	private CustomerManager $customer_manager;
	private AppointmentRepository $appointments;
	private BookingEngine $booking;
	private AvailabilityEngine $availability;
	private PaymentManager $payments;
	private Settings $settings;
	private Clock $clock;

	/**
	 * Constructor.
	 *
	 * @param CustomerRepository    $customers        Customer repository.
	 * @param CustomerManager       $customer_manager Customer manager.
	 * @param AppointmentRepository $appointments     Appointment repository.
	 * @param BookingEngine         $booking          Booking engine.
	 * @param AvailabilityEngine    $availability     Availability engine.
	 * @param PaymentManager        $payments         Payment manager.
	 * @param Settings              $settings         Settings store.
	 * @param Clock                 $clock            Clock.
	 */
	public function __construct(
		CustomerRepository $customers,
		CustomerManager $customer_manager,
		AppointmentRepository $appointments,
		BookingEngine $booking,
		AvailabilityEngine $availability,
		PaymentManager $payments,
		Settings $settings,
		Clock $clock
	) {
		$this->customers        = $customers;
		$this->customer_manager = $customer_manager;
		$this->appointments     = $appointments;
		$this->booking          = $booking;
		$this->availability     = $availability;
		$this->payments         = $payments;
		$this->settings         = $settings;
		$this->clock            = $clock;
	}

	/**
	 * Issue a magic link for an email. Never reveals whether the email exists.
	 *
	 * @param string $email Email address.
	 * @return void
	 */
	public function request_link( string $email ): void {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return;
		}
		$customer = $this->customers->find_by( array( 'email' => $email ) );
		if ( null === $customer ) {
			return;
		}

		$token = PortalToken::sign( (int) $customer['id'], (int) $this->portal( 'token_ttl_days', 14 ) );
		$base  = (string) $this->portal( 'page_url', '' );
		$base  = '' !== $base ? $base : home_url( '/' );
		$link  = add_query_arg( 'bookora_token', rawurlencode( $token ), $base );

		$business = (string) $this->settings->get( 'business_name', get_bloginfo( 'name' ) );
		$body     = sprintf(
			/* translators: 1: customer name, 2: login link */
			__( "Hi %1\$s,\n\nUse this secure link to view and manage your bookings:\n%2\$s\n\nThe link expires for your security.", 'bookora' ),
			(string) ( $customer['name'] ?? '' ),
			$link
		);

		wp_mail(
			$email,
			/* translators: %s: business name */
			sprintf( __( 'Your %s booking portal link', 'bookora' ), $business ),
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Profile fields safe to expose to the customer.
	 *
	 * @param int $customer_id Customer id.
	 * @return array<string, mixed>|null
	 */
	public function profile( int $customer_id ): ?array {
		$customer = $this->customers->find( $customer_id );
		if ( null === $customer ) {
			return null;
		}

		return array(
			'id'       => (int) $customer['id'],
			'name'     => (string) ( $customer['name'] ?? '' ),
			'email'    => (string) ( $customer['email'] ?? '' ),
			'phone'    => (string) ( $customer['phone'] ?? '' ),
			'timezone' => (string) ( $customer['timezone'] ?? '' ),
			'locale'   => (string) ( $customer['locale'] ?? '' ),
		);
	}

	/**
	 * Update the customer's own profile (email/identity is immutable here).
	 *
	 * @param int                  $customer_id Customer id.
	 * @param array<string, mixed> $input       Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_profile( int $customer_id, array $input ): array|WP_Error {
		$allowed = array_intersect_key(
			$input,
			array_flip( array( 'name', 'phone', 'timezone', 'locale' ) )
		);
		$result  = $this->customer_manager->update( $customer_id, $allowed );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return (array) $this->profile( $customer_id );
	}

	/**
	 * The customer's bookings, split into upcoming and past with policy flags.
	 *
	 * @param int $customer_id Customer id.
	 * @return array{upcoming: array<int, array<string, mixed>>, past: array<int, array<string, mixed>>}
	 */
	public function bookings( int $customer_id ): array {
		$now      = time();
		$upcoming = array();
		$past     = array();

		foreach ( $this->customers->bookings_for_customer( $customer_id, 200 ) as $row ) {
			$start  = $this->clock->utc_to_epoch( (string) $row['start_at'] );
			$active = ! in_array( (string) $row['status'], array( 'cancelled', 'no_show', 'completed' ), true );

			$row['can_reschedule'] = $active && $this->within_window( $start, (int) $this->portal( 'reschedule_min_hours', 24 ) ) && (bool) $this->portal( 'allow_reschedule', true );
			$row['can_cancel']     = $active && $this->within_window( $start, (int) $this->portal( 'cancel_min_hours', 24 ) ) && (bool) $this->portal( 'allow_cancel', true );

			if ( $start >= $now && $active ) {
				$upcoming[] = $row;
			} else {
				$past[] = $row;
			}
		}

		return array(
			'upcoming' => $upcoming,
			'past'     => $past,
		);
	}

	/**
	 * Reschedule one of the customer's bookings to a new local start time.
	 *
	 * @param int    $customer_id Customer id.
	 * @param int    $appt_id     Appointment id.
	 * @param string $start       New local start datetime.
	 * @return array<string, mixed>|WP_Error
	 */
	public function reschedule( int $customer_id, int $appt_id, string $start ): array|WP_Error {
		$appt = $this->owned_appointment( $customer_id, $appt_id );
		if ( $appt instanceof WP_Error ) {
			return $appt;
		}
		if ( ! (bool) $this->portal( 'allow_reschedule', true ) ) {
			return new WP_Error( 'bookora_forbidden', __( 'Rescheduling is disabled.', 'bookora' ), array( 'status' => 403 ) );
		}
		$current = $this->clock->utc_to_epoch( (string) $appt['start_at'] );
		if ( ! $this->within_window( $current, (int) $this->portal( 'reschedule_min_hours', 24 ) ) ) {
			return new WP_Error( 'bookora_too_late', __( 'It is too late to reschedule this booking.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( ! $this->slot_available( (int) $appt['service_id'], (int) $appt['staff_id'], $start, $appt_id ) ) {
			return new WP_Error( 'bookora_unavailable', __( 'That time is not available.', 'bookora' ), array( 'status' => 409 ) );
		}

		return $this->booking->reschedule( $appt_id, array( 'start' => $start ) );
	}

	/**
	 * Cancel one of the customer's bookings.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $appt_id     Appointment id.
	 * @return array{cancelled: bool}|WP_Error
	 */
	public function cancel( int $customer_id, int $appt_id ): array|WP_Error {
		$appt = $this->owned_appointment( $customer_id, $appt_id );
		if ( $appt instanceof WP_Error ) {
			return $appt;
		}
		if ( ! (bool) $this->portal( 'allow_cancel', true ) ) {
			return new WP_Error( 'bookora_forbidden', __( 'Cancellation is disabled.', 'bookora' ), array( 'status' => 403 ) );
		}
		$current = $this->clock->utc_to_epoch( (string) $appt['start_at'] );
		if ( ! $this->within_window( $current, (int) $this->portal( 'cancel_min_hours', 24 ) ) ) {
			return new WP_Error( 'bookora_too_late', __( 'It is too late to cancel this booking.', 'bookora' ), array( 'status' => 422 ) );
		}

		return array( 'cancelled' => $this->booking->cancel( $appt_id, 'customer' ) );
	}

	/**
	 * Invoice for one of the customer's bookings.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $appt_id     Appointment id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function invoice( int $customer_id, int $appt_id ): array|WP_Error {
		$appt = $this->owned_appointment( $customer_id, $appt_id );
		if ( $appt instanceof WP_Error ) {
			return $appt;
		}

		return $this->payments->invoice( $appt_id );
	}

	/**
	 * Fetch an appointment and confirm it belongs to the customer.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $appt_id     Appointment id.
	 * @return array<string, mixed>|WP_Error
	 */
	private function owned_appointment( int $customer_id, int $appt_id ): array|WP_Error {
		$appt = $this->appointments->find( $appt_id );
		if ( null === $appt ) {
			return new WP_Error( 'bookora_not_found', __( 'Booking not found.', 'bookora' ), array( 'status' => 404 ) );
		}
		if ( (int) $appt['customer_id'] !== $customer_id ) {
			return new WP_Error( 'bookora_forbidden', __( 'This booking is not yours.', 'bookora' ), array( 'status' => 403 ) );
		}

		return $appt;
	}

	/**
	 * Whether an appointment start is far enough out to allow a change.
	 *
	 * @param int $start_epoch Appointment start (epoch).
	 * @param int $min_hours   Minimum lead hours.
	 * @return bool
	 */
	private function within_window( int $start_epoch, int $min_hours ): bool {
		return $start_epoch >= time() + ( $min_hours * HOUR_IN_SECONDS );
	}

	/**
	 * Whether a local start is an available slot for the service/staff.
	 *
	 * @param int    $service_id Service id.
	 * @param int    $staff_id   Staff id.
	 * @param string $start      Local start datetime.
	 * @return bool
	 */
	private function slot_available( int $service_id, int $staff_id, string $start, int $ignore_id ): bool {
		unset( $ignore_id );
		$date  = substr( $start, 0, 10 );
		$slots = $this->availability->for_date( $service_id, $staff_id > 0 ? $staff_id : null, $date )['slots'];
		foreach ( $slots as $slot ) {
			if ( (string) $slot['start_local'] === $start ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read a portal setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	private function portal( string $key, mixed $default ): mixed {
		$portal = (array) $this->settings->get( 'portal', array() );

		return $portal[ $key ] ?? $default;
	}
}
