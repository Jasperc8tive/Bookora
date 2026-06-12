<?php
/**
 * Flutterwave gateway driver.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments\Gateways;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Flutterwave (cards, mobile money, bank transfer) — secondary African rail.
 */
final class FlutterwaveDriver extends AbstractGateway {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'flutterwave';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Flutterwave';
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
			'https://api.flutterwave.com/v3/payments',
			array(
				'tx_ref'       => $args['reference'],
				'amount'       => (float) $args['amount'],
				'currency'     => $args['currency'],
				'redirect_url' => $args['callback_url'],
				'customer'     => array( 'email' => $args['email'] ),
				'meta'         => $args['metadata'],
			),
			array( 'Authorization' => 'Bearer ' . $this->option( 'secret_key' ) )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$link = (string) ( $response['data']['link'] ?? '' );
		if ( '' === $link ) {
			return new WP_Error( 'bookora_gateway_error', __( 'Flutterwave did not return a payment link.', 'bookora' ), array( 'status' => 502 ) );
		}

		return array(
			'provider_ref' => $args['reference'],
			'redirect_url' => $link,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Flutterwave sends the configured secret hash in the `verif-hash` header.
	 */
	public function verify_signature( string $payload, string $signature ): bool {
		unset( $payload );
		$hash = $this->option( 'secret_hash' );

		return '' !== $hash && '' !== $signature && hash_equals( $hash, $signature );
	}

	/**
	 * {@inheritDoc}
	 */
	public function parse_event( array $payload ): ?array {
		$data = (array) ( $payload['data'] ?? array() );
		$ref  = (string) ( $data['tx_ref'] ?? '' );
		if ( '' === $ref ) {
			return null;
		}

		return array(
			'reference' => $ref,
			'status'    => 'successful' === ( $data['status'] ?? '' ) ? 'success' : (string) ( $data['status'] ?? 'failed' ),
			'amount'    => (float) ( $data['amount'] ?? 0 ),
			'currency'  => (string) ( $data['currency'] ?? '' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function refund( string $reference, float $amount, string $currency ): array|WP_Error {
		unset( $currency );
		$response = $this->request(
			'POST',
			'https://api.flutterwave.com/v3/transactions/' . rawurlencode( $reference ) . '/refund',
			array( 'amount' => $amount ),
			array( 'Authorization' => 'Bearer ' . $this->option( 'secret_key' ) )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array( 'status' => 'refunded' );
	}
}
