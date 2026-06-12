<?php
/**
 * Stripe gateway driver.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments\Gateways;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stripe Checkout — global card payments.
 */
final class StripeDriver extends AbstractGateway {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'stripe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Stripe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$config = $this->config();

		return ! empty( $config['enabled'] ) && '' !== $this->option( 'secret_key' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Stripe uses form-encoded bodies, so this driver posts directly rather than
	 * via the JSON helper.
	 */
	public function create_charge( array $args ): array|WP_Error {
		$body = array(
			'mode'                                   => 'payment',
			'success_url'                            => $args['callback_url'],
			'cancel_url'                             => $args['callback_url'],
			'client_reference_id'                    => $args['reference'],
			'customer_email'                         => $args['email'],
			'line_items[0][quantity]'                => 1,
			'line_items[0][price_data][currency]'    => strtolower( (string) $args['currency'] ),
			'line_items[0][price_data][unit_amount]' => $this->minor_units( (float) $args['amount'] ),
			'line_items[0][price_data][product_data][name]' => 'Booking ' . $args['reference'],
			'metadata[reference]'                    => $args['reference'],
		);

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->option( 'secret_key' ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $parsed ) || empty( $parsed['url'] ) ) {
			return new WP_Error( 'bookora_gateway_error', __( 'Stripe did not return a checkout URL.', 'bookora' ), array( 'status' => 502 ) );
		}

		return array(
			'provider_ref' => (string) ( $parsed['id'] ?? $args['reference'] ),
			'redirect_url' => (string) $parsed['url'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Verifies the `Stripe-Signature` header (t=…,v1=…) via HMAC-SHA256 over
	 * "{timestamp}.{payload}" using the webhook signing secret.
	 */
	public function verify_signature( string $payload, string $signature ): bool {
		$secret = $this->option( 'webhook_secret' );
		if ( '' === $secret || '' === $signature ) {
			return false;
		}

		$timestamp = '';
		$expected  = '';
		foreach ( explode( ',', $signature ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 't' === $pair[0] ) {
				$timestamp = $pair[1] ?? '';
			} elseif ( 'v1' === $pair[0] ) {
				$expected = $pair[1] ?? '';
			}
		}
		if ( '' === $timestamp || '' === $expected ) {
			return false;
		}

		$computed = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		return hash_equals( $computed, $expected );
	}

	/**
	 * {@inheritDoc}
	 */
	public function parse_event( array $payload ): ?array {
		$object = (array) ( $payload['data']['object'] ?? array() );
		$ref    = (string) ( $object['client_reference_id'] ?? ( $object['metadata']['reference'] ?? '' ) );
		if ( '' === $ref ) {
			return null;
		}

		$paid = 'paid' === ( $object['payment_status'] ?? '' ) || 'checkout.session.completed' === ( $payload['type'] ?? '' );

		return array(
			'reference' => $ref,
			'status'    => $paid ? 'success' : 'failed',
			'amount'    => (float) ( $object['amount_total'] ?? 0 ) / 100,
			'currency'  => strtoupper( (string) ( $object['currency'] ?? '' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function refund( string $reference, float $amount, string $currency ): array|WP_Error {
		unset( $currency );
		$response = wp_remote_post(
			'https://api.stripe.com/v1/refunds',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->option( 'secret_key' ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'payment_intent' => $reference,
					'amount'         => $this->minor_units( $amount ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array( 'status' => 'refunded' );
	}
}
