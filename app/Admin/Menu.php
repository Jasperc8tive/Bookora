<?php
/**
 * Admin menu registration.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Admin;

use Bookora\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level Bookora menu and the Dashboard page.
 *
 * Capability is `manage_options` in Stage 1; remapped to Bookora capabilities
 * in Stage 2.
 */
final class Menu {

	/**
	 * Menu slug for the dashboard page.
	 */
	public const SLUG = 'bookora';

	/**
	 * Capability required to see the menu.
	 */
	public const CAPABILITY = Capabilities::MANAGE_SETTINGS;

	/**
	 * Dashboard page renderer.
	 *
	 * @var DashboardPage
	 */
	private DashboardPage $dashboard;

	/**
	 * Constructor.
	 *
	 * @param DashboardPage $dashboard Dashboard renderer.
	 */
	public function __construct( DashboardPage $dashboard ) {
		$this->dashboard = $dashboard;
	}

	/**
	 * Hook menu registration.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	/**
	 * Register menu items.
	 *
	 * @return void
	 */
	public function register(): void {
		add_menu_page(
			__( 'Bookora', 'bookora' ),
			__( 'Bookora', 'bookora' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this->dashboard, 'render' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			self::SLUG,
			__( 'Dashboard', 'bookora' ),
			__( 'Dashboard', 'bookora' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Services', 'bookora' ),
			__( 'Services', 'bookora' ),
			Capabilities::MANAGE_SERVICES,
			self::SLUG . '-services',
			array( $this->dashboard, 'render' )
		);
	}
}
