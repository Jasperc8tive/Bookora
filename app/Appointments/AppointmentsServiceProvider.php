<?php
/**
 * Appointments (booking engine) service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Appointments;

use Bookora\API\Controllers\AvailabilityController;
use Bookora\API\Controllers\BookingsController;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Binds the booking engine and registers its REST controllers.
 */
final class AppointmentsServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( Clock::class );
		$container->singleton( AppointmentRepository::class );
		$container->singleton( BookingHoldRepository::class );
		$container->singleton( ConflictDetector::class );
		$container->singleton( AvailabilityEngine::class );
		$container->singleton( BookingEngine::class );

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = AvailabilityController::class;
				$controllers[] = BookingsController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		// Purge expired holds opportunistically on admin load.
		add_action(
			'admin_init',
			static function () use ( $container ): void {
				$container->get( BookingHoldRepository::class )->purge_expired( gmdate( 'Y-m-d H:i:s' ) );
			}
		);
	}
}
