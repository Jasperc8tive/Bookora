<?php
/**
 * Payment gateway driver contract.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments\Contracts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A pluggable payment gateway. Implementations wrap a single provider
 * (Paystack, Flutterwave, Stripe, …) behind a uniform interface so the
 * PaymentManager stays provider-agnostic.
 */
interface PaymentGateway {

	/**
	 * Stable machine key, e.g. "paystack".
	 *
	 * @return string
	 */
	public function key(): string;

	/**
	 * Human label for admin UIs.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Whether the gateway is enabled and has the keys it needs.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Create a hosted charge / checkout session.
	 *
	 * @param array{reference: string, amount: float, currency: string, email: string, callback_url: string, metadata: array<string,mixed>} $args Charge args.
	 * @return array{provider_ref: string, redirect_url: string}|WP_Error
	 */
	public function create_charge( array $args ): array|WP_Error;

	/**
	 * Verify a webhook signature against the raw request body.
	 *
	 * @param string $payload   Raw request body.
	 * @param string $signature Provider signature header value.
	 * @return bool
	 */
	public function verify_signature( string $payload, string $signature ): bool;

	/**
	 * Normalise a webhook payload into a provider-agnostic event.
	 *
	 * @param array<string, mixed> $payload Decoded webhook body.
	 * @return array{reference: string, status: string, amount: float, currency: string}|null
	 */
	public function parse_event( array $payload ): ?array;

	/**
	 * Refund a captured charge.
	 *
	 * @param string $reference Our payment reference.
	 * @param float  $amount    Amount to refund.
	 * @param string $currency  Currency code.
	 * @return array{status: string}|WP_Error
	 */
	public function refund( string $reference, float $amount, string $currency ): array|WP_Error;
}
