<?php
/**
 * Reports module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Reports;

use Bookora\API\Controllers\ReportsController;
use Bookora\Appointments\Clock;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Database\Schema;
use Bookora\Staff\AvailabilityRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Binds the reporting service and registers its REST controller.
 */
final class ReportsServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			ReportService::class,
			static fn ( Container $c ): ReportService => new ReportService(
				null,
				$c->get( Schema::class ),
				$c->get( Clock::class ),
				$c->get( AvailabilityRepository::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = ReportsController::class;

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
