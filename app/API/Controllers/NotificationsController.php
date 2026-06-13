<?php
/**
 * Notifications admin REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Core\Settings;
use Bookora\Notifications\ChannelRegistry;
use Bookora\Notifications\NotificationRepository;
use Bookora\Notifications\TemplateRenderer;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Notifications admin surface: channel/template settings, delivery log, test send.
 */
final class NotificationsController extends AbstractController {

	private Settings $settings;
	private NotificationRepository $log;
	private ChannelRegistry $channels;
	private TemplateRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param Settings               $settings Settings store.
	 * @param NotificationRepository $log      Delivery log.
	 * @param ChannelRegistry        $channels Channel registry.
	 * @param TemplateRenderer       $renderer Template renderer.
	 */
	public function __construct( Settings $settings, NotificationRepository $log, ChannelRegistry $channels, TemplateRenderer $renderer ) {
		$this->settings = $settings;
		$this->log      = $log;
		$this->channels = $channels;
		$this->renderer = $renderer;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$manage = $this->require_capability( Capabilities::MANAGE_SETTINGS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/notifications/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/notifications/log',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'log' ),
				'permission_callback' => $this->require_capability( Capabilities::MANAGE_BOOKINGS ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/notifications/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_send' ),
				'permission_callback' => $manage,
			)
		);
	}

	/**
	 * Delivery log.
	 *
	 * @return WP_REST_Response
	 */
	public function log(): WP_REST_Response {
		return $this->success( array( 'items' => $this->log->recent() ) );
	}

	/**
	 * Current channel/reminder/template settings (secrets masked).
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		$notif    = (array) $this->settings->get( 'notifications', array() );
		$channels = (array) ( $notif['channels'] ?? array() );

		return $this->success(
			array(
				'channels'  => array(
					'email'    => array( 'enabled' => (bool) ( $channels['email']['enabled'] ?? true ) ),
					'sms'      => array(
						'enabled'     => (bool) ( $channels['sms']['enabled'] ?? false ),
						'sender'      => (string) ( $channels['sms']['sender'] ?? '' ),
						'has_api_key' => '' !== (string) ( $channels['sms']['api_key'] ?? '' ),
					),
					'whatsapp' => array(
						'enabled'          => (bool) ( $channels['whatsapp']['enabled'] ?? false ),
						'phone_number_id'  => (string) ( $channels['whatsapp']['phone_number_id'] ?? '' ),
						'has_access_token' => '' !== (string) ( $channels['whatsapp']['access_token'] ?? '' ),
					),
					'push'     => array(
						'enabled'     => (bool) ( $channels['push']['enabled'] ?? false ),
						'endpoint'    => (string) ( $channels['push']['endpoint'] ?? '' ),
						'has_api_key' => '' !== (string) ( $channels['push']['api_key'] ?? '' ),
					),
				),
				'reminders' => array( 'offsets' => array_values( (array) ( $notif['reminders']['offsets'] ?? array( 1440, 60 ) ) ) ),
				'events'    => TemplateRenderer::events(),
				'defaults'  => TemplateRenderer::defaults(),
				'templates' => (array) ( $notif['templates'] ?? array() ),
			)
		);
	}

	/**
	 * Update channel/reminder/template settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$body    = (array) $request->get_json_params();
		$notif   = (array) $this->settings->get( 'notifications', array() );
		$current = (array) ( $notif['channels'] ?? array() );
		$input   = (array) ( $body['channels'] ?? array() );

		foreach ( array( 'email', 'sms', 'whatsapp', 'push' ) as $key ) {
			$cfg = (array) ( $current[ $key ] ?? array() );
			$in  = (array) ( $input[ $key ] ?? array() );
			if ( array_key_exists( 'enabled', $in ) ) {
				$cfg['enabled'] = (bool) $in['enabled'];
			}
			foreach ( array( 'sender', 'phone_number_id', 'endpoint' ) as $field ) {
				if ( array_key_exists( $field, $in ) ) {
					$cfg[ $field ] = sanitize_text_field( (string) $in[ $field ] );
				}
			}
			foreach ( array( 'api_key', 'access_token' ) as $field ) {
				if ( ! empty( $in[ $field ] ) ) {
					$cfg[ $field ] = sanitize_text_field( (string) $in[ $field ] );
				}
			}
			$current[ $key ] = $cfg;
		}
		$notif['channels'] = $current;

		if ( isset( $body['reminders']['offsets'] ) && is_array( $body['reminders']['offsets'] ) ) {
			$notif['reminders'] = array(
				'offsets' => array_values( array_filter( array_map( 'intval', $body['reminders']['offsets'] ), static fn ( int $m ): bool => $m > 0 ) ),
			);
		}

		if ( isset( $body['templates'] ) && is_array( $body['templates'] ) ) {
			$notif['templates'] = $this->sanitize_templates( $body['templates'] );
		}

		$this->settings->set( 'notifications', $notif );

		return $this->get_settings();
	}

	/**
	 * Send a test email to verify configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function test_send( WP_REST_Request $request ) {
		$body  = (array) $request->get_json_params();
		$email = sanitize_email( (string) ( $body['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return $this->error( 'bookora_invalid_email', __( 'Enter a valid email address.', 'bookora' ), 422 );
		}

		$context  = array(
			'customer_name' => __( 'there', 'bookora' ),
			'service_name'  => __( 'Sample service', 'bookora' ),
			'staff_name'    => __( 'Sample staff', 'bookora' ),
			'business_name' => (string) $this->settings->get( 'business_name', '' ),
			'date'          => wp_date( (string) $this->settings->get( 'date_format', 'Y-m-d' ) ),
			'time'          => wp_date( (string) $this->settings->get( 'time_format', 'H:i' ) ),
		);
		$rendered = $this->renderer->render( 'booking_created', 'email', $context );
		$channel  = $this->channels->get( 'email' );
		$result   = null !== $channel ? $channel->send( $email, $rendered, $context ) : null;

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( array( 'sent' => true ) );
	}

	/**
	 * Sanitise a templates payload.
	 *
	 * @param array<string, mixed> $templates Raw templates.
	 * @return array<string, mixed>
	 */
	private function sanitize_templates( array $templates ): array {
		$clean = array();
		foreach ( $templates as $event => $channels ) {
			if ( ! is_array( $channels ) ) {
				continue;
			}
			foreach ( $channels as $channel => $tpl ) {
				$tpl = (array) $tpl;
				$clean[ sanitize_key( (string) $event ) ][ sanitize_key( (string) $channel ) ] = array(
					'subject' => sanitize_text_field( (string) ( $tpl['subject'] ?? '' ) ),
					'body'    => sanitize_textarea_field( (string) ( $tpl['body'] ?? '' ) ),
				);
			}
		}

		return $clean;
	}
}
