<?php
/**
 * Front-end module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Frontend;

use Bookora\API\Controllers\PublicBookingController;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the public booking wizard: REST controller + shortcode.
 */
final class FrontendServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( Shortcode::class );

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = PublicBookingController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		$shortcode = $container->get( Shortcode::class );

		add_action( 'wp_enqueue_scripts', array( $shortcode, 'register_assets' ) );
		add_shortcode( 'bookora_booking', array( $shortcode, 'render' ) );
	}
}
