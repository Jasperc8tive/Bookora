<?php
/**
 * Gateway registry.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments;

use Bookora\Payments\Contracts\PaymentGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the available payment gateways keyed by their machine key.
 */
final class GatewayRegistry {

	/**
	 * Registered gateways.
	 *
	 * @var array<string, PaymentGateway>
	 */
	private array $gateways = array();

	/**
	 * Register a gateway.
	 *
	 * @param PaymentGateway $gateway Gateway.
	 * @return void
	 */
	public function add( PaymentGateway $gateway ): void {
		$this->gateways[ $gateway->key() ] = $gateway;
	}

	/**
	 * Resolve a gateway by key.
	 *
	 * @param string $key Gateway key.
	 * @return PaymentGateway|null
	 */
	public function get( string $key ): ?PaymentGateway {
		return $this->gateways[ $key ] ?? null;
	}

	/**
	 * All registered gateways.
	 *
	 * @return array<string, PaymentGateway>
	 */
	public function all(): array {
		return $this->gateways;
	}

	/**
	 * Only the gateways that are enabled and configured.
	 *
	 * @return array<string, PaymentGateway>
	 */
	public function configured(): array {
		return array_filter( $this->gateways, static fn ( PaymentGateway $g ): bool => $g->is_configured() );
	}
}
