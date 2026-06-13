<?php
/**
 * Customer portal module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Portal;

use Bookora\API\Controllers\PortalController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\BookingEngine;
use Bookora\Appointments\Clock;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Core\ModuleScript;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerManager;
use Bookora\Customers\CustomerRepository;
use Bookora\Payments\PaymentManager;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the customer portal: manager, REST controller, and the `[bookora_portal]`
 * shortcode that mounts the portal React bundle.
 */
final class PortalServiceProvider implements ServiceProvider {

	/**
	 * Script/style handle for the portal bundle.
	 */
	public const HANDLE = 'bookora-portal';

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			PortalManager::class,
			static fn ( Container $c ): PortalManager => new PortalManager(
				$c->get( CustomerRepository::class ),
				$c->get( CustomerManager::class ),
				$c->get( AppointmentRepository::class ),
				$c->get( BookingEngine::class ),
				$c->get( AvailabilityEngine::class ),
				$c->get( PaymentManager::class ),
				$c->get( Settings::class ),
				$c->get( Clock::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = PortalController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		unset( $container );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'bookora_portal', array( $this, 'render' ) );
	}

	/**
	 * Register the portal bundle.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_script( self::HANDLE, BOOKORA_URL . 'assets/build/portal.js', array(), BOOKORA_VERSION, true );
		wp_register_style( self::HANDLE, BOOKORA_URL . 'assets/build/portal.css', array(), BOOKORA_VERSION );
		wp_localize_script(
			self::HANDLE,
			'BookoraPortal',
			array(
				'restUrl' => esc_url_raw( rest_url( 'bookora/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render the `[bookora_portal]` shortcode: mount point for the portal app.
	 *
	 * @return string
	 */
	public function render(): string {
		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );
		ModuleScript::enable( self::HANDLE );

		return '<div id="bookora-portal-root"></div>';
	}
}
