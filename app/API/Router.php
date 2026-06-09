<?php
/**
 * REST router.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every Bookora REST controller on rest_api_init.
 */
final class Router {

	/**
	 * Controllers managed by the router.
	 *
	 * @var array<int, AbstractController>
	 */
	private array $controllers;

	/**
	 * Constructor.
	 *
	 * @param array<int, AbstractController> $controllers Controllers to register.
	 */
	public function __construct( array $controllers = array() ) {
		$this->controllers = $controllers;
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
	 * Register all controller routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
