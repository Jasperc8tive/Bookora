<?php
/**
 * Services module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Services;

use Bookora\API\Controllers\ServiceCategoriesController;
use Bookora\API\Controllers\ServicesController;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Binds the services module and registers its REST controllers.
 */
final class ServicesServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( ServiceRepository::class );
		$container->singleton( CategoryRepository::class );
		$container->singleton( ServiceManager::class );
		$container->singleton( CategoryManager::class );

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = ServicesController::class;
				$controllers[] = ServiceCategoriesController::class;

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
