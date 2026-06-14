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
			__( 'Calendar', 'bookora' ),
			__( 'Calendar', 'bookora' ),
			Capabilities::MANAGE_BOOKINGS,
			self::SLUG . '-calendar',
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

		add_submenu_page(
			self::SLUG,
			__( 'Staff', 'bookora' ),
			__( 'Staff', 'bookora' ),
			Capabilities::MANAGE_STAFF,
			self::SLUG . '-staff',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Customers', 'bookora' ),
			__( 'Customers', 'bookora' ),
			Capabilities::MANAGE_CUSTOMERS,
			self::SLUG . '-customers',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Payments', 'bookora' ),
			__( 'Payments', 'bookora' ),
			Capabilities::MANAGE_PAYMENTS,
			self::SLUG . '-payments',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Notifications', 'bookora' ),
			__( 'Notifications', 'bookora' ),
			Capabilities::MANAGE_SETTINGS,
			self::SLUG . '-notifications',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Integrations', 'bookora' ),
			__( 'Integrations', 'bookora' ),
			Capabilities::MANAGE_SETTINGS,
			self::SLUG . '-integrations',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Reports', 'bookora' ),
			__( 'Reports', 'bookora' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG . '-reports',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'AI Scheduling', 'bookora' ),
			__( 'AI Scheduling', 'bookora' ),
			Capabilities::MANAGE_BOOKINGS,
			self::SLUG . '-scheduling',
			array( $this->dashboard, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Advanced', 'bookora' ),
			__( 'Advanced', 'bookora' ),
			Capabilities::MANAGE_SETTINGS,
			self::SLUG . '-advanced',
			array( $this->dashboard, 'render' )
		);
	}
}
