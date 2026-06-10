<?php
/**
 * Customers module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Customers;

use Bookora\API\Controllers\CustomerNotesController;
use Bookora\API\Controllers\CustomersController;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Security\ActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Binds the customers (CRM) module and registers its REST controllers.
 */
final class CustomersServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( CustomerRepository::class );
		$container->singleton( NoteRepository::class );
		$container->singleton(
			CustomerManager::class,
			static fn ( Container $c ): CustomerManager => new CustomerManager(
				$c->get( CustomerRepository::class ),
				$c->get( NoteRepository::class ),
				$c->get( AuditLogRepository::class ),
				$c->get( ActivityLogger::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = CustomersController::class;
				$controllers[] = CustomerNotesController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		unset( $container );
	}
}
