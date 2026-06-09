<?php
/**
 * REST router.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API;

use Bookora\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every Bookora REST controller on rest_api_init.
 *
 * Controllers are gathered through the `bookora_rest_controllers` filter so
 * feature modules can register their own without modifying the API provider.
 */
final class Router {

	/**
	 * Service container (resolves controllers).
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Controller class names registered by default.
	 *
	 * @var array<int, class-string<AbstractController>>
	 */
	private array $defaults;

	/**
	 * Constructor.
	 *
	 * @param Container                                    $container Container.
	 * @param array<int, class-string<AbstractController>> $defaults  Default controllers.
	 */
	public function __construct( Container $container, array $defaults = array() ) {
		$this->container = $container;
		$this->defaults  = $defaults;
	}

	/**
	 * Hook route registration.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Resolve and register all controller routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		/**
		 * Filter the list of Bookora REST controller class names.
		 *
		 * @param array<int, class-string<AbstractController>> $controllers Controller classes.
		 */
		$classes = apply_filters( 'bookora_rest_controllers', $this->defaults );

		$seen = array();
		foreach ( (array) $classes as $class ) {
			if ( ! is_string( $class ) || isset( $seen[ $class ] ) ) {
				continue;
			}
			$seen[ $class ] = true;

			$controller = $this->container->get( $class );
			if ( $controller instanceof AbstractController ) {
				$controller->register_routes();
			}
		}
	}
}
