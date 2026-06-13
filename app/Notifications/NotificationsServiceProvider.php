<?php
/**
 * Notifications module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications;

use Bookora\API\Controllers\NotificationsController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\Clock;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerRepository;
use Bookora\Notifications\Channels\EmailChannel;
use Bookora\Notifications\Channels\PushChannel;
use Bookora\Notifications\Channels\SmsChannel;
use Bookora\Notifications\Channels\WhatsAppChannel;
use Bookora\Payments\PaymentRepository;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires channels, the dispatcher, domain-event subscribers, the reminder cron,
 * and the notifications REST controller.
 */
final class NotificationsServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( NotificationRepository::class );
		$container->singleton(
			TemplateRenderer::class,
			static fn ( Container $c ): TemplateRenderer => new TemplateRenderer( $c->get( Settings::class ) )
		);

		$container->singleton(
			ChannelRegistry::class,
			static function ( Container $c ): ChannelRegistry {
				$settings = $c->get( Settings::class );
				$registry = new ChannelRegistry();
				$registry->add( new EmailChannel( $settings ) );
				$registry->add( new SmsChannel( $settings ) );
				$registry->add( new WhatsAppChannel( $settings ) );
				$registry->add( new PushChannel( $settings ) );

				/**
				 * Allow add-ons to register notification channels.
				 *
				 * @param ChannelRegistry $registry The channel registry.
				 */
				do_action( 'bookora_register_channels', $registry );

				return $registry;
			}
		);

		$container->singleton(
			ContextBuilder::class,
			static fn ( Container $c ): ContextBuilder => new ContextBuilder(
				$c->get( AppointmentRepository::class ),
				$c->get( ServiceRepository::class ),
				$c->get( StaffRepository::class ),
				$c->get( CustomerRepository::class ),
				$c->get( PaymentRepository::class ),
				$c->get( Settings::class ),
				$c->get( Clock::class )
			)
		);

		$container->singleton(
			NotificationDispatcher::class,
			static fn ( Container $c ): NotificationDispatcher => new NotificationDispatcher(
				$c->get( ChannelRegistry::class ),
				$c->get( TemplateRenderer::class ),
				$c->get( ContextBuilder::class ),
				$c->get( NotificationRepository::class ),
				$c->get( Settings::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = NotificationsController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		$dispatcher = static fn (): NotificationDispatcher => $container->get( NotificationDispatcher::class );

		// Booking created → notify + schedule reminders (async via cron tick).
		add_action(
			'bookora_booking_created',
			static function ( int $appointment_id ) use ( $container ): void {
				$container->get( NotificationDispatcher::class )->schedule_reminders( $appointment_id );
				self::enqueue( 'booking_created', $appointment_id );
			}
		);
		add_action(
			'bookora_booking_rescheduled',
			static function ( int $appointment_id ): void {
				self::enqueue( 'booking_rescheduled', $appointment_id );
			}
		);
		add_action(
			'bookora_booking_cancelled',
			static function ( int $appointment_id ): void {
				self::enqueue( 'booking_cancelled', $appointment_id );
			}
		);
		add_action(
			'bookora_payment_succeeded',
			static function ( int $payment_id, int $appointment_id ): void {
				self::enqueue( 'payment_received', $appointment_id, $payment_id );
			},
			10,
			2
		);

		// Async worker: build context + dispatch.
		add_action(
			'bookora_notify',
			static function ( string $event, int $appointment_id, int $payment_id ) use ( $dispatcher ): void {
				$dispatcher()->for_event( $event, $appointment_id, $payment_id > 0 ? $payment_id : null );
			},
			10,
			3
		);

		// Reminder cron: only fire if the appointment is still active.
		add_action(
			'bookora_send_reminder',
			static function ( int $appointment_id ) use ( $container ): void {
				$dispatch = $container->get( NotificationDispatcher::class );
				if ( $container->get( ContextBuilder::class )->is_active( $appointment_id ) ) {
					$dispatch->for_event( 'reminder', $appointment_id );
				}
			}
		);
	}

	/**
	 * Schedule a near-immediate async dispatch so requests stay fast.
	 *
	 * @param string $event          Event key.
	 * @param int    $appointment_id Appointment id.
	 * @param int    $payment_id     Optional payment id.
	 * @return void
	 */
	private static function enqueue( string $event, int $appointment_id, int $payment_id = 0 ): void {
		wp_schedule_single_event( time(), 'bookora_notify', array( $event, $appointment_id, $payment_id ) );
	}
}
