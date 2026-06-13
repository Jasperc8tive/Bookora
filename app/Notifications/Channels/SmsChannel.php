<?php
/**
 * SMS channel (Termii).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications\Channels;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends SMS via Termii (a local African provider). Disabled until configured.
 */
final class SmsChannel extends AbstractChannel {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'sms';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'SMS';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$config = $this->config();

		return ! empty( $config['enabled'] ) && ! empty( $config['api_key'] ) && ! empty( $config['sender'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function recipient_field(): string {
		return 'customer_phone';
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $recipient, array $message, array $context ): array|WP_Error {
		unset( $context );
		if ( '' === trim( $recipient ) ) {
			return new WP_Error( 'bookora_invalid_recipient', __( 'No phone number for SMS.', 'bookora' ) );
		}
		$config = $this->config();

		$response = wp_remote_post(
			'https://api.ng.termii.com/api/sms/send',
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => (string) wp_json_encode(
					array(
						'to'      => $recipient,
						'from'    => (string) $config['sender'],
						'sms'     => $message['body'],
						'type'    => 'plain',
						'channel' => 'generic',
						'api_key' => (string) $config['api_key'],
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return new WP_Error( 'bookora_send_failed', __( 'The SMS provider rejected the message.', 'bookora' ) );
		}

		return array( 'provider_ref' => '' );
	}
}
