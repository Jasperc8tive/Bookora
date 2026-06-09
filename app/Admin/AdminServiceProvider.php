<?php
/**
 * Admin service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Admin;

use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the admin menu and asset loader (admin context only).
 */
final class AdminServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( DashboardPage::class );
		$container->singleton(
			Menu::class,
			static fn ( Container $c ): Menu => new Menu( $c->get( DashboardPage::class ) )
		);
		$container->singleton( Assets::class );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		if ( ! is_admin() ) {
			return;
		}

		$container->get( Menu::class )->init();
		$container->get( Assets::class )->init();
	}
}
