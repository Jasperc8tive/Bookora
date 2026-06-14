<?php
/**
 * Plugin orchestrator.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Bookora\Admin\AdminServiceProvider;
use Bookora\Advanced\AdvancedServiceProvider;
use Bookora\API\ApiServiceProvider;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Database\DatabaseServiceProvider;
use Bookora\Appointments\AppointmentsServiceProvider;
use Bookora\Customers\CustomersServiceProvider;
use Bookora\Elementor\ElementorServiceProvider;
use Bookora\Frontend\FrontendServiceProvider;
use Bookora\Integrations\IntegrationsServiceProvider;
use Bookora\Notifications\NotificationsServiceProvider;
use Bookora\Payments\PaymentsServiceProvider;
use Bookora\Portal\PortalServiceProvider;
use Bookora\Reports\ReportsServiceProvider;
use Bookora\Security\SecurityServiceProvider;
use Bookora\Services\ServicesServiceProvider;
use Bookora\Staff\StaffServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin: builds the container, registers providers, wires hooks,
 * and keeps the database schema current.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Provider class names, in registration order.
	 *
	 * @var array<int, class-string<ServiceProvider>>
	 */
	private array $providers = array(
		DatabaseServiceProvider::class,
		SecurityServiceProvider::class,
		ServicesServiceProvider::class,
		StaffServiceProvider::class,
		CustomersServiceProvider::class,
		AppointmentsServiceProvider::class,
		PaymentsServiceProvider::class,
		NotificationsServiceProvider::class,
		IntegrationsServiceProvider::class,
		ElementorServiceProvider::class,
		PortalServiceProvider::class,
		ReportsServiceProvider::class,
		AdvancedServiceProvider::class,
		FrontendServiceProvider::class,
		ApiServiceProvider::class,
		AdminServiceProvider::class,
	);

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {
		$this->container = new Container();
		$this->container->instance( Container::class, $this->container );
	}

	/**
	 * Retrieve the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Access the container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Boot the plugin once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'bookora', false, dirname( BOOKORA_BASENAME ) . '/languages' );

		/** @var array<int, ServiceProvider> $instances */
		$instances = array();
		foreach ( $this->providers as $provider_class ) {
			$provider = new $provider_class();
			$provider->register( $this->container );
			$instances[] = $provider;
		}

		foreach ( $instances as $provider ) {
			$provider->boot( $this->container );
		}

		// Keep schema current after plugin updates (cheap, guarded check).
		add_action( 'admin_init', array( $this, 'maybe_upgrade_database' ) );
	}

	/**
	 * Run pending migrations when the stored DB version is behind the code.
	 *
	 * @return void
	 */
	public function maybe_upgrade_database(): void {
		$installed = (string) get_option( 'bookora_db_version', '0' );
		if ( version_compare( $installed, BOOKORA_DB_VERSION, '>=' ) ) {
			return;
		}

		$runner = $this->container->get( \Bookora\Database\MigrationRunner::class );
		$runner->migrate();
		update_option( 'bookora_db_version', BOOKORA_DB_VERSION, false );
	}
}
