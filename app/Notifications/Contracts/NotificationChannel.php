<?php
/**
 * Notification channel contract.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications\Contracts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A delivery channel (email, SMS, WhatsApp, push). Implementations send a single
 * rendered message and report success or a WP_Error.
 */
interface NotificationChannel {

	/**
	 * Machine key, e.g. "email".
	 *
	 * @return string
	 */
	public function key(): string;

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Whether the channel is enabled and configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * The context field holding this channel's recipient address, e.g.
	 * "customer_email" or "customer_phone".
	 *
	 * @return string
	 */
	public function recipient_field(): string;

	/**
	 * Send a rendered message.
	 *
	 * @param string                        $recipient Recipient address.
	 * @param array{subject: string, body: string} $message Rendered message.
	 * @param array<string, mixed>          $context   Template context.
	 * @return array{provider_ref: string}|WP_Error
	 */
	public function send( string $recipient, array $message, array $context ): array|WP_Error;
}
