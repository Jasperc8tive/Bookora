<?php
/**
 * Push channel (provider webhook).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications\Channels;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Posts a push notification to a configured provider endpoint (e.g. a OneSignal
 * REST hook), addressed by the customer's email as external id. Disabled until
 * configured. Full web-push (VAPID + service worker) is a planned enhancement.
 */
final class PushChannel extends AbstractChannel {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'push';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Push';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$config = $this->config();

		return ! empty( $config['enabled'] ) && ! empty( $config['endpoint'] ) && ! empty( $config['api_key'] );
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
		$config = $this->config();

		$response = wp_remote_post(
			(string) $config['endpoint'],
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . (string) $config['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode(
					array(
						'external_id' => $recipient,
						'title'       => $message['subject'],
						'body'        => $message['body'],
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return new WP_Error( 'bookora_send_failed', __( 'The push provider rejected the message.', 'bookora' ) );
		}

		return array( 'provider_ref' => '' );
	}
}
