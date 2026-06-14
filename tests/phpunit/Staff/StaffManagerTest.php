<?php
/**
 * StaffManager + AvailabilityManager tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Staff;

use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\Security\ActivityLogger;
use Bookora\Staff\AvailabilityManager;
use Bookora\Staff\AvailabilityRepository;
use Bookora\Staff\StaffManager;
use Bookora\Staff\StaffRepository;
use Bookora\Staff\StaffServiceRepository;
use WP_Error;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Staff\StaffManager
 * @covers \Bookora\Staff\AvailabilityManager
 * @covers \Bookora\Staff\StaffServiceRepository
 */
class StaffManagerTest extends WP_UnitTestCase {

	private StaffManager $staff;
	private AvailabilityManager $availability;
	private StaffServiceRepository $assignments;

	public function set_up(): void {
		parent::set_up();

		$schema            = new Schema();
		$audit             = new ActivityLogger( new AuditLogRepository( null, $schema ) );
		$staffRepo         = new StaffRepository( null, $schema );
		$this->assignments = new StaffServiceRepository( null, $schema );
		$this->staff       = new StaffManager( $staffRepo, $this->assignments, $audit );
		$this->availability = new AvailabilityManager( new AvailabilityRepository( null, $schema ), $audit );
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_create_requires_display_name(): void {
		$result = $this->staff->create( array( 'email' => 'x@example.com' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'display_name', $result->get_error_data()['fields'] );
	}

	public function test_invalid_email_is_rejected(): void {
		$result = $this->staff->create( array( 'display_name' => 'Ada', 'email' => 'not-an-email' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_create_persists_skills_as_list(): void {
		$result = $this->staff->create( array( 'display_name' => 'Ada', 'skills' => 'Facials, Massage , Waxing' ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( 'Facials', 'Massage', 'Waxing' ), $result['skills'] );
	}

	public function test_assigned_services_are_synced(): void {
		$created = $this->staff->create( array( 'display_name' => 'Tunde', 'service_ids' => array( 3, 5, 5 ) ) );

		$ids = $this->assignments->service_ids_for_staff( (int) $created['id'] );
		sort( $ids );
		$this->assertSame( array( 3, 5 ), $ids );

		$this->staff->update( (int) $created['id'], array( 'service_ids' => array( 7 ) ) );
		$this->assertSame( array( 7 ), $this->assignments->service_ids_for_staff( (int) $created['id'] ) );
	}

	public function test_soft_delete(): void {
		$created = $this->staff->create( array( 'display_name' => 'Temp' ) );
		$this->assertTrue( $this->staff->delete( (int) $created['id'] ) );
		$this->assertNull( $this->staff->get_with_relations( (int) $created['id'] ) );
	}

	public function test_set_and_get_availability(): void {
		$created  = $this->staff->create( array( 'display_name' => 'Joy' ) );
		$staff_id = (int) $created['id'];

		$result = $this->availability->set(
			$staff_id,
			array(
				'working_hours' => array(
					array( 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00' ),
					array( 'weekday' => 2, 'start_time' => '10:00', 'end_time' => '15:00' ),
				),
				'breaks'        => array(
					array( 'weekday' => 1, 'start_time' => '12:00', 'end_time' => '13:00' ),
				),
				'time_off'      => array(
					array( 'start_date' => '2026-07-01', 'end_date' => '2026-07-05', 'note' => 'Leave' ),
				),
				'holidays'      => array(),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result['working_hours'] );
		$this->assertCount( 1, $result['breaks'] );
		$this->assertCount( 1, $result['time_off'] );

		// Replacing again should not duplicate.
		$this->availability->set( $staff_id, array( 'working_hours' => array( array( 'weekday' => 3, 'start_time' => '08:00', 'end_time' => '12:00' ) ) ) );
		$reloaded = $this->availability->get( $staff_id );
		$this->assertCount( 1, $reloaded['working_hours'] );
		$this->assertCount( 0, $reloaded['time_off'] );
	}

	public function test_availability_rejects_bad_times(): void {
		$created = $this->staff->create( array( 'display_name' => 'Joy' ) );
		$result  = $this->availability->set(
			(int) $created['id'],
			array( 'working_hours' => array( array( 'weekday' => 1, 'start_time' => '17:00', 'end_time' => '09:00' ) ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_availability_rejects_bad_weekday(): void {
		$created = $this->staff->create( array( 'display_name' => 'Joy' ) );
		$result  = $this->availability->set(
			(int) $created['id'],
			array( 'working_hours' => array( array( 'weekday' => 9, 'start_time' => '09:00', 'end_time' => '17:00' ) ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_search_and_paginate(): void {
		$this->staff->create( array( 'display_name' => 'Ada Lovelace', 'email' => 'ada@example.com' ) );
		$this->staff->create( array( 'display_name' => 'Tunde Bello' ) );

		$repo = new StaffRepository();
		$this->assertSame( 1, $repo->paginate( array( 'search' => 'Ada' ) )['total'] );
		$this->assertSame( 2, $repo->paginate( array() )['total'] );
	}
}
