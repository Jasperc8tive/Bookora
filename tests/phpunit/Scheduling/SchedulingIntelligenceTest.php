<?php
/**
 * AI scheduling (heuristic scorer + scheduling intelligence) tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Scheduling;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\AvailabilityRepository;
use Bookora\Appointments\BookingEngine;
use Bookora\Appointments\BookingHoldRepository;
use Bookora\Appointments\Clock;
use Bookora\Appointments\ConflictDetector;
use Bookora\Customers\CustomerRepository;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\Scheduling\HeuristicScorer;
use Bookora\Scheduling\SchedulingIntelligence;
use Bookora\Security\ActivityLogger;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Staff\StaffServiceRepository;
use DateTimeZone;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Scheduling\HeuristicScorer
 * @covers \Bookora\Scheduling\SchedulingIntelligence
 */
class SchedulingIntelligenceTest extends WP_UnitTestCase {

	private const DATE = '2030-06-12'; // A fixed future Wednesday.

	private MigrationRunner $runner;
	private Schema $schema;
	private ServiceRepository $services;
	private StaffRepository $staff;
	private StaffServiceRepository $assignments;
	private AvailabilityRepository $availability_repo;
	private CustomerRepository $customers;
	private AppointmentRepository $appointments;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		$this->schema            = new Schema();
		$this->services          = new ServiceRepository( null, $this->schema );
		$this->staff             = new StaffRepository( null, $this->schema );
		$this->assignments       = new StaffServiceRepository( null, $this->schema );
		$this->availability_repo = new AvailabilityRepository( null, $this->schema );
		$this->customers         = new CustomerRepository( null, $this->schema );
		$this->appointments      = new AppointmentRepository( null, $this->schema );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	/**
	 * @return array{0: AvailabilityEngine, 1: BookingEngine, 2: Clock, 3: SchedulingIntelligence}
	 */
	private function build(): array {
		$clock     = new Clock( new DateTimeZone( 'UTC' ) );
		$holds     = new BookingHoldRepository( null, $this->schema );
		$conflicts = new ConflictDetector( $this->appointments, $holds, $clock );
		$audit     = new ActivityLogger( new AuditLogRepository( null, $this->schema ) );

		$availability = new AvailabilityEngine(
			$this->services,
			$this->availability_repo,
			$this->assignments,
			$this->appointments,
			$holds,
			$clock
		);
		$booking      = new BookingEngine(
			$this->services,
			$this->staff,
			$this->customers,
			$this->appointments,
			$holds,
			$conflicts,
			$clock,
			$audit
		);
		$ai           = new SchedulingIntelligence(
			$this->schema,
			$availability,
			$this->assignments,
			$this->appointments,
			$clock,
			new HeuristicScorer()
		);

		return array( $availability, $booking, $clock, $ai );
	}

	private function seed_service(): int {
		return $this->services->create(
			array(
				'name'         => 'Haircut',
				'duration_min' => 30,
				'price'        => 50,
				'currency'     => 'NGN',
				'capacity'     => 1,
				'status'       => 'active',
			)
		);
	}

	private function seed_staff( Clock $clock, int $service_id, string $name ): int {
		$staff_id = $this->staff->create( array( 'display_name' => $name, 'status' => 'active' ) );
		$this->assignments->sync_for_staff( $staff_id, array( $service_id ) );
		$this->availability_repo->create(
			array(
				'staff_id'   => $staff_id,
				'type'       => 'working_hours',
				'weekday'    => $clock->weekday( self::DATE ),
				'start_time' => '09:00:00',
				'end_time'   => '17:00:00',
			)
		);

		return $staff_id;
	}

	public function test_heuristic_scorer_rewards_preference_adjacency_and_soonest(): void {
		$scorer = new HeuristicScorer();

		$morning = $scorer->score(
			array( 'start_local' => '2030-06-12 09:00:00' ),
			array( 'prefer' => 'morning', 'adjacent' => false, 'days_from_now' => 100 )
		);
		$afternoon = $scorer->score(
			array( 'start_local' => '2030-06-12 14:00:00' ),
			array( 'prefer' => 'morning', 'adjacent' => false, 'days_from_now' => 100 )
		);
		$this->assertGreaterThan( $afternoon, $morning, 'Preferred band should score higher.' );

		$adjacent = $scorer->score(
			array( 'start_local' => '2030-06-12 14:00:00' ),
			array( 'prefer' => '', 'adjacent' => true, 'days_from_now' => 100 )
		);
		$apart = $scorer->score(
			array( 'start_local' => '2030-06-12 14:00:00' ),
			array( 'prefer' => '', 'adjacent' => false, 'days_from_now' => 100 )
		);
		$this->assertGreaterThan( $apart, $adjacent, 'Adjacent slots should score higher.' );

		$soon = $scorer->score(
			array( 'start_local' => '2030-06-12 14:00:00' ),
			array( 'prefer' => '', 'adjacent' => false, 'days_from_now' => 0 )
		);
		$this->assertGreaterThan( $apart, $soon, 'Sooner slots should score higher.' );
	}

	public function test_suggest_returns_ranked_slots_biased_to_preference(): void {
		list( , , $clock, $ai ) = $this->build();
		$service                = $this->seed_service();
		$this->seed_staff( $clock, $service, 'Ada' );

		$items = $ai->suggest( $service, self::DATE, self::DATE, 'morning', 3 );

		$this->assertCount( 3, $items );
		$this->assertArrayHasKey( 'start_local', $items[0] );
		$this->assertArrayHasKey( 'score', $items[0] );
		// Top suggestion should fall in the preferred morning band.
		$hour = (int) substr( $items[0]['start_local'], 11, 2 );
		$this->assertLessThanOrEqual( 11, $hour );
	}

	public function test_auto_assign_picks_least_loaded_staff(): void {
		list( , $booking, $clock, $ai ) = $this->build();
		$service                        = $this->seed_service();
		$ada                            = $this->seed_staff( $clock, $service, 'Ada' );
		$bola                           = $this->seed_staff( $clock, $service, 'Bola' );

		// Give Ada an upcoming appointment so Bola is the lighter-loaded option.
		$booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $ada,
				'customer_id' => $this->customers->create( array( 'name' => 'Joy' ) ),
				'start'       => self::DATE . ' 10:00:00',
			)
		);

		$this->assertSame( $bola, $ai->auto_assign( $service, self::DATE . ' 09:00:00' ) );
	}

	public function test_workload_and_forecast_shapes(): void {
		list( , , $clock, $ai ) = $this->build();
		$service                = $this->seed_service();
		$this->seed_staff( $clock, $service, 'Ada' );

		$workload = $ai->workload();
		$this->assertNotEmpty( $workload );
		$this->assertArrayHasKey( 'upcoming', $workload[0] );

		$forecast = $ai->forecast( 14 );
		$this->assertCount( 7, $forecast['by_weekday'] );
		$this->assertCount( 14, $forecast['next_days'] );
		$this->assertArrayHasKey( 'predicted', $forecast['next_days'][0] );
	}
}
