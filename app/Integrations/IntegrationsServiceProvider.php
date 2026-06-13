<?php
/**
 * Integrations module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations;

use Bookora\API\Controllers\GoogleCalendarController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Customers\CustomerRepository;
use Bookora\Integrations\Google\CalendarSyncService;
use Bookora\Integrations\Google\GoogleClient;
use Bookora\Integrations\Google\GoogleTokenStore;
use Bookora\Security\Crypto;
use Bookora\Services\ServiceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires Google Calendar: token store, client, sync service, the external-busy
 * availability filter, booking-event push/delete, and the REST controller.
 */
final class IntegrationsServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( IntegrationRepository::class );
		$container->singleton( Crypto::class );
		$container->singleton(
			GoogleTokenStore::class,
			static fn ( Container $c ): GoogleTokenStore => new GoogleTokenStore(
				$c->get( IntegrationRepository::class ),
				$c->get( Crypto::class )
			)
		);
		$container->singleton( GoogleClient::class );
		$container->singleton(
			CalendarSyncService::class,
			static fn ( Container $c ): CalendarSyncService => new CalendarSyncService(
				$c->get( GoogleTokenStore::class ),
				$c->get( GoogleClient::class ),
				$c->get( AppointmentRepository::class ),
				$c->get( ServiceRepository::class ),
				$c->get( CustomerRepository::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = GoogleCalendarController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		// Feed Google busy intervals into availability.
		add_filter(
			'bookora_external_busy',
			static function ( array $busy, int $staff_id, string $from, string $to ) use ( $container ): array {
				return $container->get( CalendarSyncService::class )->filter_busy( $busy, $staff_id, $from, $to );
			},
			10,
			4
		);

		// Push/delete on booking lifecycle (async).
		$schedule = static function ( string $hook ): callable {
			return static function ( int $id ) use ( $hook ): void {
				wp_schedule_single_event( time(), $hook, array( $id ) );
			};
		};
		add_action( 'bookora_booking_created', $schedule( 'bookora_gcal_push' ) );
		add_action( 'bookora_booking_rescheduled', $schedule( 'bookora_gcal_push' ) );
		add_action( 'bookora_booking_cancelled', $schedule( 'bookora_gcal_delete' ) );

		add_action(
			'bookora_gcal_push',
			static function ( int $id ) use ( $container ): void {
				$container->get( CalendarSyncService::class )->push_for_appointment( $id );
			}
		);
		add_action(
			'bookora_gcal_delete',
			static function ( int $id ) use ( $container ): void {
				$container->get( CalendarSyncService::class )->delete_for_appointment( $id );
			}
		);

		// Periodically warm the busy cache for all connected staff.
		add_action(
			'bookora_gcal_warm',
			static function () use ( $container ): void {
				$sync   = $container->get( CalendarSyncService::class );
				$tokens = $container->get( GoogleTokenStore::class );
				foreach ( $tokens->connected_staff_ids() as $staff_id ) {
					$sync->warm_busy_cache( $staff_id );
				}
			}
		);
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'bookora_gcal_warm' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'bookora_gcal_warm' );
		}
	}
}
