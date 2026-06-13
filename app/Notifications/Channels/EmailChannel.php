<?php
/**
 * Email channel.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications\Channels;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends email via wp_mail(). Enabled by default — the baseline channel.
 */
final class EmailChannel extends AbstractChannel {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$config = $this->config();

		// Email defaults to on unless explicitly disabled.
		return ! array_key_exists( 'enabled', $config ) || ! empty( $config['enabled'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function recipient_field(): string {
		return 'customer_email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $recipient, array $message, array $context ): array|WP_Error {
		unset( $context );
		if ( ! is_email( $recipient ) ) {
			return new WP_Error( 'bookora_invalid_recipient', __( 'Invalid email recipient.', 'bookora' ) );
		}

		$sent = wp_mail(
			$recipient,
			$message['subject'],
			$message['body'],
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);

		if ( ! $sent ) {
			return new WP_Error( 'bookora_send_failed', __( 'The email could not be sent.', 'bookora' ) );
		}

		return array( 'provider_ref' => '' );
	}
}
