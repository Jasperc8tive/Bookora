<?php
/**
 * WhatsApp channel (Cloud API).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications\Channels;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends WhatsApp messages via the Meta Cloud API — the headline channel for the
 * target market. Sends free-form text (valid inside the 24h window); template
 * messages for outside-window sends are a follow-up.
 */
final class WhatsAppChannel extends AbstractChannel {

	/**
	 * {@inheritDoc}
	 */
	public function key(): string {
		return 'whatsapp';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WhatsApp';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$config = $this->config();

		return ! empty( $config['enabled'] ) && ! empty( $config['phone_number_id'] ) && ! empty( $config['access_token'] );
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
		$recipient = preg_replace( '/[^0-9]/', '', $recipient );
		if ( '' === (string) $recipient ) {
			return new WP_Error( 'bookora_invalid_recipient', __( 'No phone number for WhatsApp.', 'bookora' ) );
		}
		$config = $this->config();

		$response = wp_remote_post(
			'https://graph.facebook.com/v19.0/' . rawurlencode( (string) $config['phone_number_id'] ) . '/messages',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . (string) $config['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode(
					array(
						'messaging_product' => 'whatsapp',
						'to'                => $recipient,
						'type'              => 'text',
						'text'              => array( 'body' => $message['body'] ),
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return new WP_Error( 'bookora_send_failed', __( 'WhatsApp rejected the message.', 'bookora' ) );
		}

		$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ref    = is_array( $parsed ) ? (string) ( $parsed['messages'][0]['id'] ?? '' ) : '';

		return array( 'provider_ref' => $ref );
	}
}
