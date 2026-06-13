<?php
/**
 * Notification template renderer.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications;

use Bookora\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves per-event/per-channel templates (custom from settings, else built-in
 * defaults) and renders them with {{placeholder}} substitution.
 */
final class TemplateRenderer {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Built-in default templates, keyed by event then channel.
	 *
	 * Channels that only carry a body (sms/whatsapp/push) use the `body` only.
	 *
	 * @return array<string, array<string, array{subject: string, body: string}>>
	 */
	public static function defaults(): array {
		$created = 'Hi {{customer_name}}, your booking for {{service_name}} on {{date}} at {{time}} is confirmed. — {{business_name}}';
		$remind  = 'Reminder: {{service_name}} on {{date}} at {{time}}. See you soon! — {{business_name}}';
		$resch   = 'Your booking was moved to {{date}} at {{time}} for {{service_name}}. — {{business_name}}';
		$cancel  = 'Your booking for {{service_name}} on {{date}} has been cancelled. — {{business_name}}';
		$paid    = 'We received your payment of {{currency}} {{amount}} for {{service_name}}. Thank you! — {{business_name}}';

		$build = static fn ( string $subject, string $body ): array => array(
			'email'    => array(
				'subject' => $subject,
				'body'    => $body,
			),
			'sms'      => array(
				'subject' => '',
				'body'    => $body,
			),
			'whatsapp' => array(
				'subject' => '',
				'body'    => $body,
			),
			'push'     => array(
				'subject' => $subject,
				'body'    => $body,
			),
		);

		return array(
			'booking_created'     => $build( 'Your booking is confirmed', $created ),
			'reminder'            => $build( 'Appointment reminder', $remind ),
			'booking_rescheduled' => $build( 'Your booking was rescheduled', $resch ),
			'booking_cancelled'   => $build( 'Your booking was cancelled', $cancel ),
			'payment_received'    => $build( 'Payment received', $paid ),
		);
	}

	/**
	 * The events Bookora can notify on.
	 *
	 * @return array<int, string>
	 */
	public static function events(): array {
		return array_keys( self::defaults() );
	}

	/**
	 * Render an event/channel template with the given context.
	 *
	 * @param string               $event   Event key.
	 * @param string               $channel Channel key.
	 * @param array<string, mixed> $context Placeholder values.
	 * @return array{subject: string, body: string}
	 */
	public function render( string $event, string $channel, array $context ): array {
		$template = $this->template( $event, $channel );

		return array(
			'subject' => $this->substitute( $template['subject'], $context ),
			'body'    => $this->substitute( $template['body'], $context ),
		);
	}

	/**
	 * Resolve a template (custom override or default) for an event/channel.
	 *
	 * @param string $event   Event key.
	 * @param string $channel Channel key.
	 * @return array{subject: string, body: string}
	 */
	private function template( string $event, string $channel ): array {
		$defaults  = self::defaults();
		$fallback  = $defaults[ $event ][ $channel ] ?? array(
			'subject' => '',
			'body'    => '',
		);
		$notif     = (array) $this->settings->get( 'notifications', array() );
		$templates = (array) ( $notif['templates'] ?? array() );
		$custom    = (array) ( $templates[ $event ][ $channel ] ?? array() );

		$subject = isset( $custom['subject'] ) && '' !== $custom['subject'] ? (string) $custom['subject'] : (string) $fallback['subject'];
		$body    = isset( $custom['body'] ) && '' !== $custom['body'] ? (string) $custom['body'] : (string) $fallback['body'];

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Replace {{key}} tokens with context values.
	 *
	 * @param string               $text    Template text.
	 * @param array<string, mixed> $context Values.
	 * @return string
	 */
	private function substitute( string $text, array $context ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
			static function ( array $m ) use ( $context ): string {
				$key = $m[1];

				return array_key_exists( $key, $context ) ? (string) $context[ $key ] : '';
			},
			$text
		);
	}
}
