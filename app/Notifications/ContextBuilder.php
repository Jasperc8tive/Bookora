<?php
/**
 * Notification context builder.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\Clock;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerRepository;
use Bookora\Payments\PaymentRepository;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Assembles the placeholder context for a notification from an appointment
 * (and, for payment events, a payment).
 */
final class ContextBuilder {

	private AppointmentRepository $appointments;
	private ServiceRepository $services;
	private StaffRepository $staff;
	private CustomerRepository $customers;
	private PaymentRepository $payments;
	private Settings $settings;
	private Clock $clock;

	/**
	 * Constructor.
	 *
	 * @param AppointmentRepository $appointments Appointment repository.
	 * @param ServiceRepository     $services     Service repository.
	 * @param StaffRepository       $staff        Staff repository.
	 * @param CustomerRepository    $customers    Customer repository.
	 * @param PaymentRepository     $payments     Payment repository.
	 * @param Settings              $settings     Settings store.
	 * @param Clock                 $clock        Clock.
	 */
	public function __construct(
		AppointmentRepository $appointments,
		ServiceRepository $services,
		StaffRepository $staff,
		CustomerRepository $customers,
		PaymentRepository $payments,
		Settings $settings,
		Clock $clock
	) {
		$this->appointments = $appointments;
		$this->services     = $services;
		$this->staff        = $staff;
		$this->customers    = $customers;
		$this->payments     = $payments;
		$this->settings     = $settings;
		$this->clock        = $clock;
	}

	/**
	 * Build the context for an appointment (and optional payment).
	 *
	 * @param int      $appointment_id Appointment id.
	 * @param int|null $payment_id     Optional payment id.
	 * @return array<string, mixed>|null Null when the appointment is missing.
	 */
	public function build( int $appointment_id, ?int $payment_id = null ): ?array {
		$appt = $this->appointments->find( $appointment_id );
		if ( null === $appt ) {
			return null;
		}

		$service  = $this->services->find( (int) $appt['service_id'] );
		$customer = $this->customers->find( (int) $appt['customer_id'] );
		$member   = $appt['staff_id'] ? $this->staff->find( (int) $appt['staff_id'] ) : null;
		$epoch    = $this->clock->utc_to_epoch( (string) $appt['start_at'] );

		$amount   = (float) $appt['total'];
		$currency = (string) $appt['currency'];
		if ( null !== $payment_id ) {
			$payment = $this->payments->find( $payment_id );
			if ( null !== $payment ) {
				$amount   = (float) $payment['amount'];
				$currency = (string) $payment['currency'];
			}
		}

		return array(
			'booking_id'     => (int) $appt['id'],
			'customer_name'  => (string) ( $customer['name'] ?? '' ),
			'customer_email' => (string) ( $customer['email'] ?? '' ),
			'customer_phone' => (string) ( $customer['phone'] ?? '' ),
			'service_name'   => (string) ( $service['name'] ?? '' ),
			'staff_name'     => (string) ( $member['display_name'] ?? '' ),
			'business_name'  => (string) $this->settings->get( 'business_name', '' ),
			'date'           => wp_date( (string) $this->settings->get( 'date_format', 'Y-m-d' ), $epoch ),
			'time'           => wp_date( (string) $this->settings->get( 'time_format', 'H:i' ), $epoch ),
			'status'         => (string) $appt['status'],
			'amount'         => number_format( $amount, 2 ),
			'currency'       => $currency,
		);
	}

	/**
	 * Expose the appointment's UTC start epoch (for reminder scheduling).
	 *
	 * @param int $appointment_id Appointment id.
	 * @return int|null
	 */
	public function start_epoch( int $appointment_id ): ?int {
		$appt = $this->appointments->find( $appointment_id );

		return null === $appt ? null : $this->clock->utc_to_epoch( (string) $appt['start_at'] );
	}

	/**
	 * Whether an appointment is still active (not cancelled/deleted).
	 *
	 * @param int $appointment_id Appointment id.
	 * @return bool
	 */
	public function is_active( int $appointment_id ): bool {
		$appt = $this->appointments->find( $appointment_id );

		return null !== $appt && 'cancelled' !== $appt['status'];
	}
}
