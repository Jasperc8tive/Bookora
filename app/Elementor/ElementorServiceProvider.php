<?php
/**
 * Elementor module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Elementor;

use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Bookora widget category and native widgets with Elementor.
 *
 * Everything here is gated on Elementor being active. Widget classes are
 * referenced only via dynamic class-name strings (and instantiated only inside
 * the Elementor hook), so they are never loaded when Elementor is absent and
 * never need to resolve the Elementor base class.
 */
final class ElementorServiceProvider implements ServiceProvider {

	/**
	 * Fully-qualified widget class names.
	 *
	 * @var array<int, string>
	 */
	private const WIDGETS = array(
		'Bookora\\Elementor\\Widgets\\BookingFormWidget',
		'Bookora\\Elementor\\Widgets\\ServiceGridWidget',
		'Bookora\\Elementor\\Widgets\\StaffGridWidget',
		'Bookora\\Elementor\\Widgets\\CalendarWidget',
		'Bookora\\Elementor\\Widgets\\CustomerDashboardWidget',
	);

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		unset( $container );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		unset( $container );

		if ( ! defined( 'ELEMENTOR_VERSION' ) && ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register the "Bookora" widget category.
	 *
	 * @param object $manager Elementor elements manager.
	 * @return void
	 */
	public function register_category( object $manager ): void {
		if ( ! method_exists( $manager, 'add_category' ) ) {
			return;
		}
		$manager->add_category(
			'bookora',
			array(
				'title' => __( 'Bookora', 'bookora' ),
				'icon'  => 'eicon-calendar',
			)
		);
	}

	/**
	 * Register each Bookora widget.
	 *
	 * @param object $manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( object $manager ): void {
		if ( ! method_exists( $manager, 'register' ) ) {
			return;
		}
		foreach ( self::WIDGETS as $class ) {
			if ( class_exists( $class ) ) {
				$manager->register( new $class() );
			}
		}
	}
}
