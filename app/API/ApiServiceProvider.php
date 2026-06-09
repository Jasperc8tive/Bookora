<?php
/**
 * REST API service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API;

use Bookora\API\Controllers\SystemController;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the REST router and its controllers.
 */
final class ApiServiceProvider implements ServiceProvider {

	/**
	 * Controller classes registered with the router.
	 *
	 * @var array<int, class-string<AbstractController>>
	 */
	private array $controllers = array(
		SystemController::class,
	);

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$controllers = $this->controllers;
		$container->singleton(
			Router::class,
			static function ( Container $c ) use ( $controllers ): Router {
				$instances = array_map(
					static fn ( string $class ): AbstractController => $c->get( $class ),
					$controllers
				);

				return new Router( $instances );
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		$container->get( Router::class )->init();
	}
}
