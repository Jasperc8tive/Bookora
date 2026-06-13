<?php
/**
 * Payment application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerRepository;
use Bookora\Security\ActivityLogger;
use Bookora\Services\ServiceRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates charges, webhooks, refunds, manual payments, and documents,
 * keeping appointment payment state authoritative on the server.
 */
final class PaymentManager {

	private PaymentRepository $payments;
	private AppointmentRepository $appointments;
	private ServiceRepository $services;
	private CustomerRepository $customers;
	private GatewayRegistry $gateways;
	private Settings $settings;
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param PaymentRepository     $payments     Payment repository.
	 * @param AppointmentRepository $appointments Appointment repository.
	 * @param ServiceRepository     $services     Service repository.
	 * @param CustomerRepository    $customers    Customer repository.
	 * @param GatewayRegistry       $gateways     Gateway registry.
	 * @param Settings              $settings     Settings store.
	 * @param ActivityLogger        $audit        Audit logger.
	 */
	public function __construct(
		PaymentRepository $payments,
		AppointmentRepository $appointments,
		ServiceRepository $services,
		CustomerRepository $customers,
		GatewayRegistry $gateways,
		Settings $settings,
		ActivityLogger $audit
	) {
		$this->payments     = $payments;
		$this->appointments = $appointments;
		$this->services     = $services;
		$this->customers    = $customers;
		$this->gateways     = $gateways;
		$this->settings     = $settings;
		$this->audit        = $audit;
	}

	/**
	 * Enabled gateways for UI (key + label).
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	public function available_gateways(): array {
		$out = array();
		foreach ( $this->gateways->configured() as $gateway ) {
			$out[] = array(
				'key'   => $gateway->key(),
				'label' => $gateway->label(),
			);
		}

		return $out;
	}

	/**
	 * Start a hosted payment for an appointment.
	 *
	 * @param int    $appointment_id Appointment id.
	 * @param string $gateway_key    Gateway key.
	 * @param string $type           "full" or "deposit".
	 * @param string $callback_url   Return URL.
	 * @return array{payment_id: int, reference: string, redirect_url: string}|WP_Error
	 */
	public function initialize( int $appointment_id, string $gateway_key, string $type, string $callback_url ): array|WP_Error {
		$appt = $this->appointments->find( $appointment_id );
		if ( null === $appt ) {
			return new WP_Error( 'bookora_not_found', __( 'Appointment not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		$gateway = $this->gateways->get( $gateway_key );
		if ( null === $gateway || ! $gateway->is_configured() ) {
			return new WP_Error( 'bookora_gateway_unavailable', __( 'That payment method is not available.', 'bookora' ), array( 'status' => 422 ) );
		}

		$service = $this->services->find( (int) $appt['service_id'] );
		$amount  = $this->amount_for( $appt, $service, $type );
		if ( $amount <= 0 ) {
			return new WP_Error( 'bookora_nothing_due', __( 'There is nothing to pay.', 'bookora' ), array( 'status' => 422 ) );
		}

		$reference  = 'bkra_' . wp_generate_password( 24, false, false );
		$customer   = $this->customers->find( (int) $appt['customer_id'] );
		$email      = (string) ( $customer['email'] ?? '' );
		$payment_id = $this->payments->create(
			array(
				'appointment_id' => $appointment_id,
				'customer_id'    => (int) $appt['customer_id'],
				'gateway'        => $gateway_key,
				'gateway_ref'    => $reference,
				'amount'         => $amount,
				'currency'       => (string) $appt['currency'],
				'status'         => 'pending',
				'type'           => 'deposit' === $type ? 'deposit' : 'full',
			)
		);

		$charge = $gateway->create_charge(
			array(
				'reference'    => $reference,
				'amount'       => $amount,
				'currency'     => (string) $appt['currency'],
				'email'        => $email,
				'callback_url' => $callback_url,
				'metadata'     => array(
					'appointment_id' => $appointment_id,
					'payment_id'     => $payment_id,
				),
			)
		);
		if ( is_wp_error( $charge ) ) {
			$this->payments->update( $payment_id, array( 'status' => 'failed' ) );
			return $charge;
		}

		$this->payments->update(
			$payment_id,
			array( 'meta' => (string) wp_json_encode( array( 'provider_ref' => $charge['provider_ref'] ) ) )
		);

		return array(
			'payment_id'   => $payment_id,
			'reference'    => $reference,
			'redirect_url' => $charge['redirect_url'],
		);
	}

	/**
	 * Verify and apply a gateway webhook.
	 *
	 * @param string $gateway_key Gateway key.
	 * @param string $raw_payload Raw request body.
	 * @param string $signature   Provider signature header.
	 * @return array{handled: bool, reason?: string}|WP_Error
	 */
	public function handle_webhook( string $gateway_key, string $raw_payload, string $signature ): array|WP_Error {
		$gateway = $this->gateways->get( $gateway_key );
		if ( null === $gateway ) {
			return new WP_Error( 'bookora_not_found', __( 'Unknown gateway.', 'bookora' ), array( 'status' => 404 ) );
		}
		if ( ! $gateway->verify_signature( $raw_payload, $signature ) ) {
			return new WP_Error( 'bookora_invalid_signature', __( 'Invalid signature.', 'bookora' ), array( 'status' => 401 ) );
		}

		$decoded = json_decode( $raw_payload, true );
		$event   = is_array( $decoded ) ? $gateway->parse_event( $decoded ) : null;
		if ( null === $event ) {
			return array(
				'handled' => false,
				'reason'  => 'unparseable',
			);
		}

		$payment = $this->payments->find_by_reference( $gateway_key, $event['reference'] );
		if ( null === $payment ) {
			return array(
				'handled' => false,
				'reason'  => 'unknown_reference',
			);
		}
		if ( 'paid' === $payment['status'] ) {
			return array( 'handled' => true );
		}
		if ( 'success' !== $event['status'] ) {
			$this->payments->update( (int) $payment['id'], array( 'status' => 'failed' ) );
			return array(
				'handled' => true,
				'reason'  => 'not_successful',
			);
		}

		// Server-authoritative amount/currency check.
		if ( abs( (float) $event['amount'] - (float) $payment['amount'] ) > 0.01
			|| strtoupper( (string) $event['currency'] ) !== strtoupper( (string) $payment['currency'] ) ) {
			$this->payments->update( (int) $payment['id'], array( 'status' => 'flagged' ) );
			$this->audit->log(
				'payment.amount_mismatch',
				array(
					'entity_type' => 'payment',
					'entity_id'   => (int) $payment['id'],
				)
			);
			return array(
				'handled' => false,
				'reason'  => 'amount_mismatch',
			);
		}

		$this->mark_paid( $payment );

		return array( 'handled' => true );
	}

	/**
	 * Record an offline (cash/transfer) payment.
	 *
	 * @param int    $appointment_id Appointment id.
	 * @param float  $amount         Amount.
	 * @param string $method         Method label (cash/transfer).
	 * @return array<string, mixed>|WP_Error
	 */
	public function record_manual( int $appointment_id, float $amount, string $method ): array|WP_Error {
		$appt = $this->appointments->find( $appointment_id );
		if ( null === $appt ) {
			return new WP_Error( 'bookora_not_found', __( 'Appointment not found.', 'bookora' ), array( 'status' => 404 ) );
		}
		if ( $amount <= 0 ) {
			return new WP_Error( 'bookora_invalid_amount', __( 'Amount must be greater than zero.', 'bookora' ), array( 'status' => 422 ) );
		}

		$id = $this->payments->create(
			array(
				'appointment_id' => $appointment_id,
				'customer_id'    => (int) $appt['customer_id'],
				'gateway'        => 'manual',
				'gateway_ref'    => 'manual_' . wp_generate_password( 18, false, false ),
				'amount'         => $amount,
				'currency'       => (string) $appt['currency'],
				'status'         => 'paid',
				'type'           => 'full',
				'paid_at'        => current_time( 'mysql', true ),
				'meta'           => (string) wp_json_encode( array( 'method' => sanitize_text_field( $method ) ) ),
			)
		);
		$this->apply_to_appointment( $appt, $amount );
		$this->audit->log(
			'payment.manual_recorded',
			array(
				'entity_type' => 'payment',
				'entity_id'   => $id,
			)
		);

		return (array) $this->payments->find( $id );
	}

	/**
	 * Refund a payment (full or partial).
	 *
	 * @param int   $payment_id Payment id.
	 * @param float $amount     Amount to refund (0 = full).
	 * @return array<string, mixed>|WP_Error
	 */
	public function refund( int $payment_id, float $amount = 0.0 ): array|WP_Error {
		$payment = $this->payments->find( $payment_id );
		if ( null === $payment || 'paid' !== $payment['status'] ) {
			return new WP_Error( 'bookora_not_refundable', __( 'This payment cannot be refunded.', 'bookora' ), array( 'status' => 422 ) );
		}

		$amount  = $amount > 0 ? min( $amount, (float) $payment['amount'] ) : (float) $payment['amount'];
		$gateway = $this->gateways->get( (string) $payment['gateway'] );

		if ( null !== $gateway ) {
			$provider_ref = $this->provider_ref( $payment );
			$result       = $gateway->refund( $provider_ref, $amount, (string) $payment['currency'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$refund_id = $this->payments->create(
			array(
				'appointment_id' => (int) $payment['appointment_id'],
				'customer_id'    => (int) $payment['customer_id'],
				'gateway'        => (string) $payment['gateway'],
				'gateway_ref'    => 'refund_' . wp_generate_password( 18, false, false ),
				'amount'         => $amount,
				'currency'       => (string) $payment['currency'],
				'status'         => 'refunded',
				'type'           => 'refund',
				'paid_at'        => current_time( 'mysql', true ),
			)
		);

		$appt = $this->appointments->find( (int) $payment['appointment_id'] );
		if ( null !== $appt ) {
			$this->apply_to_appointment( $appt, -$amount );
		}
		$this->audit->log(
			'payment.refunded',
			array(
				'entity_type' => 'payment',
				'entity_id'   => $payment_id,
			)
		);

		return (array) $this->payments->find( $refund_id );
	}

	/**
	 * Public-safe status of a payment by reference.
	 *
	 * @param string $reference Reference.
	 * @return array{status: string, amount: float, type: string}|null
	 */
	public function status( string $reference ): ?array {
		$payment = $this->payments->find_by_any_reference( $reference );
		if ( null === $payment ) {
			return null;
		}

		return array(
			'status' => (string) $payment['status'],
			'amount' => (float) $payment['amount'],
			'type'   => (string) $payment['type'],
		);
	}

	/**
	 * Build an invoice document for an appointment.
	 *
	 * @param int $appointment_id Appointment id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function invoice( int $appointment_id ): array|WP_Error {
		$appt = $this->appointments->find( $appointment_id );
		if ( null === $appt ) {
			return new WP_Error( 'bookora_not_found', __( 'Appointment not found.', 'bookora' ), array( 'status' => 404 ) );
		}
		$service  = $this->services->find( (int) $appt['service_id'] );
		$customer = $this->customers->find( (int) $appt['customer_id'] );

		return array(
			'number'      => 'INV-' . str_pad( (string) $appointment_id, 6, '0', STR_PAD_LEFT ),
			'business'    => (string) $this->settings->get( 'business_name', '' ),
			'date'        => (string) $appt['created_at'],
			'customer'    => (string) ( $customer['name'] ?? '' ),
			'currency'    => (string) $appt['currency'],
			'lines'       => array(
				array(
					'description' => (string) ( $service['name'] ?? __( 'Service', 'bookora' ) ),
					'amount'      => (float) $appt['total'],
				),
			),
			'total'       => (float) $appt['total'],
			'amount_paid' => (float) $appt['amount_paid'],
			'balance_due' => (float) $appt['balance_due'],
		);
	}

	/**
	 * Build a receipt document for a single payment.
	 *
	 * @param int $payment_id Payment id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function receipt( int $payment_id ): array|WP_Error {
		$payment = $this->payments->find( $payment_id );
		if ( null === $payment ) {
			return new WP_Error( 'bookora_not_found', __( 'Payment not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		return array(
			'number'   => 'RCP-' . str_pad( (string) $payment_id, 6, '0', STR_PAD_LEFT ),
			'business' => (string) $this->settings->get( 'business_name', '' ),
			'date'     => (string) ( $payment['paid_at'] ?? $payment['created_at'] ),
			'gateway'  => (string) $payment['gateway'],
			'amount'   => (float) $payment['amount'],
			'currency' => (string) $payment['currency'],
			'type'     => (string) $payment['type'],
			'status'   => (string) $payment['status'],
		);
	}

	/**
	 * Mark a payment paid and apply it to its appointment.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @return void
	 */
	private function mark_paid( array $payment ): void {
		$this->payments->update(
			(int) $payment['id'],
			array(
				'status'           => 'paid',
				'webhook_verified' => 1,
				'paid_at'          => current_time( 'mysql', true ),
			)
		);
		$appt = $this->appointments->find( (int) $payment['appointment_id'] );
		if ( null !== $appt ) {
			$this->apply_to_appointment( $appt, (float) $payment['amount'] );
		}
		$this->audit->log(
			'payment.succeeded',
			array(
				'entity_type' => 'payment',
				'entity_id'   => (int) $payment['id'],
			)
		);

		/**
		 * Fires after a payment is confirmed.
		 *
		 * @param int $payment_id     The payment id.
		 * @param int $appointment_id The related appointment id.
		 */
		do_action( 'bookora_payment_succeeded', (int) $payment['id'], (int) $payment['appointment_id'] );
	}

	/**
	 * Adjust an appointment's paid/balance totals (and confirm when settled).
	 *
	 * @param array<string, mixed> $appt   Appointment row.
	 * @param float                $delta  Signed amount applied.
	 * @return void
	 */
	private function apply_to_appointment( array $appt, float $delta ): void {
		$paid    = max( 0, (float) $appt['amount_paid'] + $delta );
		$balance = max( 0, (float) $appt['total'] - $paid );
		$update  = array(
			'amount_paid' => $paid,
			'balance_due' => $balance,
		);
		if ( $delta > 0 && $balance <= 0.01 && 'pending' === $appt['status'] ) {
			$update['status'] = 'confirmed';
		}
		$this->appointments->update( (int) $appt['id'], $update );
	}

	/**
	 * Compute the amount due for a payment type.
	 *
	 * @param array<string, mixed>      $appt    Appointment row.
	 * @param array<string, mixed>|null $service Service row.
	 * @param string                    $type    "full" or "deposit".
	 * @return float
	 */
	private function amount_for( array $appt, ?array $service, string $type ): float {
		$balance = max( 0, (float) $appt['total'] - (float) $appt['amount_paid'] );
		if ( 'deposit' !== $type || null === $service ) {
			return $balance;
		}

		$deposit = $this->deposit_amount( $service, (float) $appt['total'] );

		return $deposit > 0 ? min( $deposit, $balance ) : $balance;
	}

	/**
	 * Compute a service's deposit amount.
	 *
	 * @param array<string, mixed> $service Service row.
	 * @param float                $total   Appointment total.
	 * @return float
	 */
	private function deposit_amount( array $service, float $total ): float {
		return match ( (string) ( $service['deposit_type'] ?? 'none' ) ) {
			'fixed'   => min( (float) $service['deposit_value'], $total ),
			'percent' => round( $total * (float) $service['deposit_value'] / 100, 2 ),
			default   => 0.0,
		};
	}

	/**
	 * Resolve the provider reference used for refunds.
	 *
	 * @param array<string, mixed> $payment Payment row.
	 * @return string
	 */
	private function provider_ref( array $payment ): string {
		$meta = json_decode( (string) ( $payment['meta'] ?? '' ), true );
		if ( is_array( $meta ) && ! empty( $meta['provider_ref'] ) ) {
			return (string) $meta['provider_ref'];
		}

		return (string) $payment['gateway_ref'];
	}
}
