<?php
/**
 * Shared gateway behaviour.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments\Gateways;

use Bookora\Core\Settings;
use Bookora\Payments\Contracts\PaymentGateway;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Base class providing settings access and an HTTP helper to concrete drivers.
 */
abstract class AbstractGateway implements PaymentGateway {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Read this gateway's config block from settings.
	 *
	 * @return array<string, mixed>
	 */
	protected function config(): array {
		$payments = (array) $this->settings->get( 'payments', array() );

		return (array) ( $payments[ $this->key() ] ?? array() );
	}

	/**
	 * Read a single config value.
	 *
	 * @param string $name    Config key.
	 * @param string $default Fallback.
	 * @return string
	 */
	protected function option( string $name, string $default = '' ): string {
		$config = $this->config();

		return isset( $config[ $name ] ) ? (string) $config[ $name ] : $default;
	}

	/**
	 * Perform a JSON HTTP request to the provider.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     Endpoint URL.
	 * @param array<string, mixed> $body    Request body (JSON-encoded).
	 * @param array<string, string> $headers Extra headers.
	 * @return array<string, mixed>|WP_Error Decoded JSON response.
	 */
	protected function request( string $method, string $url, array $body = array(), array $headers = array() ): array|WP_Error {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
		);
		if ( array() !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'bookora_gateway_error', __( 'Unexpected gateway response.', 'bookora' ), array( 'status' => 502 ) );
		}
		if ( $code >= 400 ) {
			return new WP_Error(
				'bookora_gateway_error',
				__( 'The payment gateway rejected the request.', 'bookora' ),
				array(
					'status'   => 502,
					'response' => $parsed,
				)
			);
		}

		return $parsed;
	}

	/**
	 * Convert a major-unit amount to the smallest currency unit (e.g. kobo/cents).
	 *
	 * @param float $amount Amount in major units.
	 * @return int
	 */
	protected function minor_units( float $amount ): int {
		return (int) round( $amount * 100 );
	}
}
