<?php
/**
 * Database service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database;

use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Binds the schema helper and migration runner into the container.
 */
final class DatabaseServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( Schema::class, static fn (): Schema => new Schema() );
		$container->singleton(
			MigrationRunner::class,
			static fn ( Container $c ): MigrationRunner => new MigrationRunner( null, $c->get( Schema::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		// No hooks needed at boot; schema upgrades are handled by Plugin::maybe_upgrade_database().
		unset( $container );
	}
}
