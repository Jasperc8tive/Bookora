<?php
/**
 * Staff module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Staff;

use Bookora\API\Controllers\StaffAvailabilityController;
use Bookora\API\Controllers\StaffController;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Binds the staff module and registers its REST controllers.
 */
final class StaffServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( StaffRepository::class );
		$container->singleton( AvailabilityRepository::class );
		$container->singleton( StaffServiceRepository::class );
		$container->singleton( StaffManager::class );
		$container->singleton( AvailabilityManager::class );

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = StaffController::class;
				$controllers[] = StaffAvailabilityController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		unset( $container );
	}
}
