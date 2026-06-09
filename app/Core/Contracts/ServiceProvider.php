<?php
/**
 * Service provider contract.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core\Contracts;

use Bookora\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * A service provider binds services into the container and wires WordPress hooks.
 */
interface ServiceProvider {

	/**
	 * Register bindings into the container. Runs before boot().
	 *
	 * @param Container $container The container.
	 * @return void
	 */
	public function register( Container $container ): void;

	/**
	 * Wire WordPress hooks. Runs after all providers are registered.
	 *
	 * @param Container $container The container.
	 * @return void
	 */
	public function boot( Container $container ): void;
}
