<?php
/**
 * Test double gateway.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Payments\Fixtures;

use Bookora\Payments\Contracts\PaymentGateway;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A configurable in-memory gateway for testing the PaymentManager without HTTP.
 */
final class FakeGateway implements PaymentGateway {

	public bool $signature_ok = true;
	public bool $refunded     = false;

	public function key(): string {
		return 'fake';
	}

	public function label(): string {
		return 'Fake';
	}

	public function is_configured(): bool {
		return true;
	}

	public function create_charge( array $args ): array|WP_Error {
		return array(
			'provider_ref' => 'prov_' . $args['reference'],
			'redirect_url' => 'https://pay.example.test/' . $args['reference'],
		);
	}

	public function verify_signature( string $payload, string $signature ): bool {
		unset( $payload, $signature );

		return $this->signature_ok;
	}

	public function parse_event( array $payload ): ?array {
		if ( empty( $payload['reference'] ) ) {
			return null;
		}

		return array(
			'reference' => (string) $payload['reference'],
			'status'    => (string) ( $payload['status'] ?? 'success' ),
			'amount'    => (float) ( $payload['amount'] ?? 0 ),
			'currency'  => (string) ( $payload['currency'] ?? 'NGN' ),
		);
	}

	public function refund( string $reference, float $amount, string $currency ): array|WP_Error {
		unset( $reference, $amount, $currency );
		$this->refunded = true;

		return array( 'status' => 'refunded' );
	}
}
