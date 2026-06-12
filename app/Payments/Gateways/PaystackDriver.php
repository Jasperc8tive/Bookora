<?php
/**
 * Paystack gateway driver.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments\Gateways;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Paystack (cards, bank transfer, USSD) — a primary African rail.
 */
final class PaystackDriver extends AbstractGateway {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'paystack';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Paystack';
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
	 */
	public function create_charge( array $args ): array|WP_Error {
		$response = $this->request(
			'POST',
			'https://api.paystack.co/transaction/initialize',
			array(
				'email'        => $args['email'],
				'amount'       => $this->minor_units( (float) $args['amount'] ),
				'currency'     => $args['currency'],
				'reference'    => $args['reference'],
				'callback_url' => $args['callback_url'],
				'metadata'     => $args['metadata'],
			),
			array( 'Authorization' => 'Bearer ' . $this->option( 'secret_key' ) )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = (array) ( $response['data'] ?? array() );
		if ( empty( $data['authorization_url'] ) ) {
			return new WP_Error( 'bookora_gateway_error', __( 'Paystack did not return a checkout URL.', 'bookora' ), array( 'status' => 502 ) );
		}

		return array(
			'provider_ref' => (string) ( $data['reference'] ?? $args['reference'] ),
			'redirect_url' => (string) $data['authorization_url'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Paystack signs the raw body with HMAC-SHA512 using the secret key.
	 */
	public function verify_signature( string $payload, string $signature ): bool {
		$secret = $this->option( 'secret_key' );
		if ( '' === $secret || '' === $signature ) {
			return false;
		}

		return hash_equals( hash_hmac( 'sha512', $payload, $secret ), $signature );
	}

	/**
	 * {@inheritDoc}
	 */
	public function parse_event( array $payload ): ?array {
		$data = (array) ( $payload['data'] ?? array() );
		if ( empty( $data['reference'] ) ) {
			return null;
		}

		return array(
			'reference' => (string) $data['reference'],
			'status'    => 'success' === ( $payload['event'] ?? '' ) || 'success' === ( $data['status'] ?? '' ) ? 'success' : (string) ( $data['status'] ?? 'failed' ),
			'amount'    => (float) ( $data['amount'] ?? 0 ) / 100,
			'currency'  => (string) ( $data['currency'] ?? '' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function refund( string $reference, float $amount, string $currency ): array|WP_Error {
		$response = $this->request(
			'POST',
			'https://api.paystack.co/refund',
			array(
				'transaction' => $reference,
				'amount'      => $this->minor_units( $amount ),
			),
			array( 'Authorization' => 'Bearer ' . $this->option( 'secret_key' ) )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array( 'status' => 'refunded' );
	}
}
