<?php
/**
 * AI scheduling module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Scheduling;

use Bookora\API\Controllers\SchedulingController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\Clock;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Database\Schema;
use Bookora\Staff\StaffServiceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the slot scorer and the scheduling-intelligence service (smart slot
 * suggestions, load-balancing auto-assignment, demand forecasting, workload)
 * plus its REST controller.
 */
final class SchedulingServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( SlotScorer::class, static fn (): SlotScorer => new HeuristicScorer() );

		$container->singleton(
			SchedulingIntelligence::class,
			static fn ( Container $c ): SchedulingIntelligence => new SchedulingIntelligence(
				$c->get( Schema::class ),
				$c->get( AvailabilityEngine::class ),
				$c->get( StaffServiceRepository::class ),
				$c->get( AppointmentRepository::class ),
				$c->get( Clock::class ),
				$c->get( SlotScorer::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = SchedulingController::class;

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
