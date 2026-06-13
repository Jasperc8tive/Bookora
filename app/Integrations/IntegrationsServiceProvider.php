<?php
/**
 * Integrations module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations;

use Bookora\API\Controllers\GoogleCalendarController;
use Bookora\API\Controllers\OutlookCalendarController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Customers\CustomerRepository;
use Bookora\Integrations\Google\CalendarSyncService;
use Bookora\Integrations\Google\GoogleClient;
use Bookora\Integrations\Google\GoogleTokenStore;
use Bookora\Integrations\Microsoft\GraphClient;
use Bookora\Integrations\Microsoft\MicrosoftTokenStore;
use Bookora\Integrations\Microsoft\OutlookSyncService;
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

		$container->singleton(
			MicrosoftTokenStore::class,
			static fn ( Container $c ): MicrosoftTokenStore => new MicrosoftTokenStore(
				$c->get( IntegrationRepository::class ),
				$c->get( Crypto::class )
			)
		);
		$container->singleton( GraphClient::class );
		$container->singleton(
			OutlookSyncService::class,
			static fn ( Container $c ): OutlookSyncService => new OutlookSyncService(
				$c->get( MicrosoftTokenStore::class ),
				$c->get( GraphClient::class ),
				$c->get( AppointmentRepository::class ),
				$c->get( ServiceRepository::class ),
				$c->get( CustomerRepository::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = GoogleCalendarController::class;
				$controllers[] = OutlookCalendarController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		$providers = array(
			array(
				'sync'   => CalendarSyncService::class,
				'store'  => GoogleTokenStore::class,
				'push'   => 'bookora_gcal_push',
				'delete' => 'bookora_gcal_delete',
				'warm'   => 'bookora_gcal_warm',
			),
			array(
				'sync'   => OutlookSyncService::class,
				'store'  => MicrosoftTokenStore::class,
				'push'   => 'bookora_outlook_push',
				'delete' => 'bookora_outlook_delete',
				'warm'   => 'bookora_outlook_warm',
			),
		);

		$schedule = static function ( string $hook ): callable {
			return static function ( int $id ) use ( $hook ): void {
				wp_schedule_single_event( time(), $hook, array( $id ) );
			};
		};

		foreach ( $providers as $provider ) {
			$sync_class  = $provider['sync'];
			$store_class = $provider['store'];

			// Feed external busy intervals into availability.
			add_filter(
				'bookora_external_busy',
				static function ( array $busy, int $staff_id, string $from, string $to ) use ( $container, $sync_class ): array {
					return $container->get( $sync_class )->filter_busy( $busy, $staff_id, $from, $to );
				},
				10,
				4
			);

			// Push/delete on booking lifecycle (async).
			add_action( 'bookora_booking_created', $schedule( $provider['push'] ) );
			add_action( 'bookora_booking_rescheduled', $schedule( $provider['push'] ) );
			add_action( 'bookora_booking_cancelled', $schedule( $provider['delete'] ) );

			add_action(
				$provider['push'],
				static function ( int $id ) use ( $container, $sync_class ): void {
					$container->get( $sync_class )->push_for_appointment( $id );
				}
			);
			add_action(
				$provider['delete'],
				static function ( int $id ) use ( $container, $sync_class ): void {
					$container->get( $sync_class )->delete_for_appointment( $id );
				}
			);

			// Periodically warm the busy cache for all connected staff.
			add_action(
				$provider['warm'],
				static function () use ( $container, $sync_class, $store_class ): void {
					$sync = $container->get( $sync_class );
					foreach ( $container->get( $store_class )->connected_staff_ids() as $staff_id ) {
						$sync->warm_busy_cache( $staff_id );
					}
				}
			);
			if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( $provider['warm'] ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', $provider['warm'] );
			}
		}
	}
}
