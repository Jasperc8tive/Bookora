<?php
/**
 * Notification dispatcher.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications;

use Bookora\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and delivers an event's notification across every enabled channel,
 * recording each attempt to the notifications log.
 */
final class NotificationDispatcher {

	private ChannelRegistry $channels;
	private TemplateRenderer $renderer;
	private ContextBuilder $context;
	private NotificationRepository $log;
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param ChannelRegistry        $channels Channel registry.
	 * @param TemplateRenderer       $renderer Template renderer.
	 * @param ContextBuilder         $context  Context builder.
	 * @param NotificationRepository $log      Delivery log.
	 * @param Settings               $settings Settings store.
	 */
	public function __construct(
		ChannelRegistry $channels,
		TemplateRenderer $renderer,
		ContextBuilder $context,
		NotificationRepository $log,
		Settings $settings
	) {
		$this->channels = $channels;
		$this->renderer = $renderer;
		$this->context  = $context;
		$this->log      = $log;
		$this->settings = $settings;
	}

	/**
	 * Build context for an appointment and dispatch an event across channels.
	 *
	 * @param string   $event          Event key.
	 * @param int      $appointment_id Appointment id.
	 * @param int|null $payment_id     Optional payment id.
	 * @return void
	 */
	public function for_event( string $event, int $appointment_id, ?int $payment_id = null ): void {
		$context = $this->context->build( $appointment_id, $payment_id );
		if ( null === $context ) {
			return;
		}
		$this->dispatch( $event, $context, $appointment_id );
	}

	/**
	 * Dispatch a fully-built context across all enabled channels.
	 *
	 * @param string               $event          Event key.
	 * @param array<string, mixed> $context        Template context.
	 * @param int                  $appointment_id Appointment id (for the log).
	 * @return void
	 */
	public function dispatch( string $event, array $context, int $appointment_id = 0 ): void {
		foreach ( $this->channels->configured() as $channel ) {
			$recipient = (string) ( $context[ $channel->recipient_field() ] ?? '' );
			$rendered  = $this->renderer->render( $event, $channel->key(), $context );

			$record = array(
				'appointment_id' => $appointment_id > 0 ? $appointment_id : null,
				'customer_id'    => (int) ( $context['customer_id'] ?? 0 ) > 0 ? (int) $context['customer_id'] : null,
				'channel'        => $channel->key(),
				'event'          => $event,
				'template_key'   => $event,
				'recipient'      => $recipient,
				'attempts'       => 1,
			);

			if ( '' === trim( $recipient ) ) {
				$record['status'] = 'skipped';
				$record['error']  = 'no_recipient';
				$this->log->create( $record );
				continue;
			}

			$result = $channel->send( $recipient, $rendered, $context );
			if ( is_wp_error( $result ) ) {
				$record['status'] = 'failed';
				$record['error']  = mb_substr( $result->get_error_message(), 0, 255 );
			} else {
				$record['status']       = 'sent';
				$record['provider_ref'] = (string) ( $result['provider_ref'] ?? '' );
				$record['sent_at']      = current_time( 'mysql', true );
			}
			$this->log->create( $record );
		}
	}

	/**
	 * Schedule reminder notifications at the configured offsets before start.
	 *
	 * @param int $appointment_id Appointment id.
	 * @return void
	 */
	public function schedule_reminders( int $appointment_id ): void {
		$start = $this->context->start_epoch( $appointment_id );
		if ( null === $start ) {
			return;
		}

		foreach ( $this->reminder_offsets() as $minutes ) {
			$when = $start - ( $minutes * MINUTE_IN_SECONDS );
			if ( $when <= time() ) {
				continue;
			}
			if ( ! wp_next_scheduled( 'bookora_send_reminder', array( $appointment_id, $minutes ) ) ) {
				wp_schedule_single_event( $when, 'bookora_send_reminder', array( $appointment_id, $minutes ) );
			}
		}
	}

	/**
	 * Reminder offsets (minutes before start) from settings.
	 *
	 * @return array<int, int>
	 */
	private function reminder_offsets(): array {
		$notif   = (array) $this->settings->get( 'notifications', array() );
		$offsets = $notif['reminders']['offsets'] ?? array( 1440, 60 );

		return array_values( array_filter( array_map( 'intval', (array) $offsets ), static fn ( int $m ): bool => $m > 0 ) );
	}
}
