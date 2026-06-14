<?php
/**
 * Booking engine + availability tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Appointments;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\BookingEngine;
use Bookora\Appointments\BookingHoldRepository;
use Bookora\Appointments\Clock;
use Bookora\Appointments\ConflictDetector;
use Bookora\Customers\CustomerRepository;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\Security\ActivityLogger;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\AvailabilityRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Staff\StaffServiceRepository;
use DateTimeZone;
use WP_Error;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Appointments\BookingEngine
 * @covers \Bookora\Appointments\AvailabilityEngine
 */
class BookingEngineTest extends WP_UnitTestCase {

	private const DATE = '2030-06-12'; // A fixed future date.

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
	 * Build engines bound to a specific timezone.
	 *
	 * @param string $tz Timezone name.
	 * @return array{0: AvailabilityEngine, 1: BookingEngine, 2: Clock}
	 */
	private function engines( string $tz = 'UTC' ): array {
		$clock     = new Clock( new DateTimeZone( $tz ) );
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

		return array( $availability, $booking, $clock );
	}

	/**
	 * Seed a service.
	 *
	 * @param array<string, mixed> $overrides Column overrides.
	 * @return int
	 */
	private function seed_service( array $overrides = array() ): int {
		return $this->services->create(
			array_merge(
				array(
					'name'         => 'Haircut',
					'duration_min' => 30,
					'price'        => 50,
					'currency'     => 'NGN',
					'capacity'     => 1,
					'status'       => 'active',
				),
				$overrides
			)
		);
	}

	/**
	 * Seed a staff member with 09:00–17:00 working hours on the test date's weekday.
	 *
	 * @param Clock $clock     Clock (for weekday).
	 * @param int   $service_id Service to assign.
	 * @return int
	 */
	private function seed_staff( Clock $clock, int $service_id ): int {
		$staff_id = $this->staff->create( array( 'display_name' => 'Ada', 'status' => 'active' ) );
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

	private function seed_customer(): int {
		return $this->customers->create( array( 'name' => 'Joy ' . wp_generate_password( 4, false ) ) );
	}

	public function test_availability_generates_back_to_back_slots(): void {
		list( $availability, , $clock ) = $this->engines();
		$service                        = $this->seed_service();
		$this->seed_staff( $clock, $service );

		$slots = $availability->for_date( $service, null, self::DATE )['slots'];
		// 09:00–17:00 in 30-min steps = 16 slots (last start 16:30).
		$this->assertCount( 16, $slots );
		$this->assertSame( self::DATE . ' 09:00:00', $slots[0]['start_local'] );
	}

	public function test_booking_removes_the_slot_and_blocks_double_booking(): void {
		list( $availability, $booking, $clock ) = $this->engines();
		$service                                = $this->seed_service();
		$staff_id                               = $this->seed_staff( $clock, $service );
		$customer                               = $this->seed_customer();

		$result = $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $customer,
				'start'       => self::DATE . ' 09:00:00',
			)
		);
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['created'] );

		$this->assertCount( 15, $availability->for_date( $service, $staff_id, self::DATE )['slots'] );

		$dup = $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $this->seed_customer(),
				'start'       => self::DATE . ' 09:00:00',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $dup );
		$this->assertSame( 409, $dup->get_error_data()['status'] );
	}

	public function test_buffer_blocks_adjacent_slot(): void {
		list( $availability, $booking, $clock ) = $this->engines();
		$service                                = $this->seed_service( array( 'buffer_after_min' => 15 ) );
		$staff_id                               = $this->seed_staff( $clock, $service );

		$booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $this->seed_customer(),
				'start'       => self::DATE . ' 09:00:00',
			)
		);

		// 09:00 appt + 15m after-buffer blocks the 09:30 start (its before edge is 09:30 < 09:45).
		$starts = array_column( $availability->for_date( $service, $staff_id, self::DATE )['slots'], 'start_local' );
		$this->assertNotContains( self::DATE . ' 09:00:00', $starts );
		$this->assertNotContains( self::DATE . ' 09:30:00', $starts );
		$this->assertContains( self::DATE . ' 10:00:00', $starts );
	}

	public function test_group_capacity_allows_multiple_until_full(): void {
		list( $availability, $booking, $clock ) = $this->engines();
		$service                                = $this->seed_service( array( 'capacity' => 3 ) );
		$staff_id                               = $this->seed_staff( $clock, $service );

		for ( $i = 0; $i < 3; $i++ ) {
			$res = $booking->create(
				array(
					'service_id'  => $service,
					'staff_id'    => $staff_id,
					'customer_id' => $this->seed_customer(),
					'start'       => self::DATE . ' 09:00:00',
				)
			);
			$this->assertIsArray( $res, "Booking {$i} should succeed" );
		}

		$full = $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $this->seed_customer(),
				'start'       => self::DATE . ' 09:00:00',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $full );

		$starts = array_column( $availability->for_date( $service, $staff_id, self::DATE )['slots'], 'start_local' );
		$this->assertNotContains( self::DATE . ' 09:00:00', $starts );
	}

	public function test_recurring_series_links_to_parent(): void {
		list( , $booking, $clock ) = $this->engines();
		$service                   = $this->seed_service();
		$staff_id                  = $this->seed_staff( $clock, $service );

		$result = $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $this->seed_customer(),
				'start'       => self::DATE . ' 09:00:00',
				'recurrence'  => array( 'frequency' => 'weekly', 'interval' => 1, 'count' => 3 ),
			)
		);

		$this->assertCount( 3, $result['created'] );
		$this->assertSame( (int) $result['created'][0]['id'], (int) $result['created'][1]['parent_recurring_id'] );
	}

	public function test_reschedule_frees_the_old_slot(): void {
		list( $availability, $booking, $clock ) = $this->engines();
		$service                                = $this->seed_service();
		$staff_id                               = $this->seed_staff( $clock, $service );

		$id = (int) $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $this->seed_customer(),
				'start'       => self::DATE . ' 09:00:00',
			)
		)['created'][0]['id'];

		$moved = $booking->reschedule( $id, array( 'start' => self::DATE . ' 10:00:00' ) );
		$this->assertIsArray( $moved );

		$starts = array_column( $availability->for_date( $service, $staff_id, self::DATE )['slots'], 'start_local' );
		$this->assertContains( self::DATE . ' 09:00:00', $starts );
		$this->assertNotContains( self::DATE . ' 10:00:00', $starts );
	}

	public function test_cancel_frees_the_slot(): void {
		list( $availability, $booking, $clock ) = $this->engines();
		$service                                = $this->seed_service();
		$staff_id                               = $this->seed_staff( $clock, $service );

		$id = (int) $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $this->seed_customer(),
				'start'       => self::DATE . ' 09:00:00',
			)
		)['created'][0]['id'];

		$this->assertTrue( $booking->cancel( $id ) );
		$starts = array_column( $availability->for_date( $service, $staff_id, self::DATE )['slots'], 'start_local' );
		$this->assertContains( self::DATE . ' 09:00:00', $starts );
	}

	public function test_timezone_is_converted_to_utc_for_storage(): void {
		list( , $booking, ) = $this->engines( 'Africa/Lagos' ); // UTC+1, no DST.
		$service            = $this->seed_service();
		$clock_lagos        = new Clock( new DateTimeZone( 'Africa/Lagos' ) );
		$staff_id           = $this->seed_staff( $clock_lagos, $service );

		$id   = (int) $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $this->seed_customer(),
				'start'       => self::DATE . ' 09:00:00',
			)
		)['created'][0]['id'];
		$appt = $this->appointments->find( $id );

		// 09:00 Lagos == 08:00 UTC.
		$this->assertSame( self::DATE . ' 08:00:00', $appt['start_at'] );
	}

	public function test_idempotency_key_prevents_duplicates(): void {
		list( , $booking, $clock ) = $this->engines();
		$service                   = $this->seed_service();
		$staff_id                  = $this->seed_staff( $clock, $service );
		$customer                  = $this->seed_customer();

		$payload = array(
			'service_id'      => $service,
			'staff_id'        => $staff_id,
			'customer_id'     => $customer,
			'start'           => self::DATE . ' 11:00:00',
			'idempotency_key' => 'abc-123',
		);

		$first  = $booking->create( $payload );
		$second = $booking->create( $payload );

		$this->assertSame( (int) $first['created'][0]['id'], (int) $second['created'][0]['id'] );
	}

	/**
	 * HOTFIX-003: when the per-staff advisory lock cannot be acquired, create()
	 * must fail closed (409 busy) rather than enter the critical section and
	 * risk a double-booking.
	 */
	public function test_create_fails_closed_when_lock_unavailable(): void {
		$clock     = new Clock( new DateTimeZone( 'UTC' ) );
		$holds     = new BookingHoldRepository( null, $this->schema );
		$conflicts = new ConflictDetector( $this->appointments, $holds, $clock );
		$audit     = new ActivityLogger( new AuditLogRepository( null, $this->schema ) );

		// A wpdb whose GET_LOCK never grants (returns 0).
		$wpdb = $this->createMock( \wpdb::class );
		$wpdb->method( 'get_var' )->willReturn( '0' );

		$booking = new BookingEngine(
			$this->services,
			$this->staff,
			$this->customers,
			$this->appointments,
			$holds,
			$conflicts,
			$clock,
			$audit,
			$wpdb
		);

		$service  = $this->seed_service();
		$staff_id = $this->seed_staff( $clock, $service );
		$customer = $this->seed_customer();

		$result = $booking->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $staff_id,
				'customer_id' => $customer,
				'start'       => self::DATE . ' 09:00:00',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'bookora_busy', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );

		// And nothing was written.
		$this->assertCount( 16, ( new AvailabilityEngine(
			$this->services,
			$this->availability_repo,
			$this->assignments,
			$this->appointments,
			$holds,
			$clock
		) )->for_date( $service, $staff_id, self::DATE )['slots'] );
	}
}
