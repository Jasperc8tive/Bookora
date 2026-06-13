<?php
/**
 * ReportService aggregation tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Reports;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\Clock;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use Bookora\Reports\ReportService;
use Bookora\Staff\AvailabilityRepository;
use Bookora\Staff\StaffRepository;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Reports\ReportService
 */
class ReportServiceTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private ReportService $reports;
	private AppointmentRepository $appointments;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		$schema             = new Schema();
		$this->appointments = new AppointmentRepository( null, $schema );
		$this->reports      = new ReportService( null, $schema, new Clock(), new AvailabilityRepository( null, $schema ) );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	private function appt( string $status, float $total, float $paid, string $start, ?int $staff = 1 ): void {
		$this->appointments->create(
			array(
				'customer_id' => 1,
				'service_id'  => 1,
				'staff_id'    => $staff,
				'start_at'    => $start,
				'end_at'      => gmdate( 'Y-m-d H:i:s', strtotime( $start ) + 3600 ),
				'status'      => $status,
				'total'       => $total,
				'amount_paid' => $paid,
				'currency'    => 'NGN',
			)
		);
	}

	public function test_overview_kpis_and_breakdowns(): void {
		$this->appt( 'completed', 100, 100, '2030-06-10 09:00:00' );
		$this->appt( 'confirmed', 50, 0, '2030-06-10 11:00:00' );
		$this->appt( 'cancelled', 80, 0, '2030-06-11 09:00:00' );
		$this->appt( 'no_show', 40, 0, '2030-06-11 12:00:00' );

		$o = $this->reports->overview( '2030-06-01 00:00:00', '2030-07-01 00:00:00' );

		$this->assertSame( 4, $o['kpis']['total_bookings'] );
		$this->assertSame( 1, $o['kpis']['completed'] );
		$this->assertSame( 1, $o['kpis']['cancelled'] );
		$this->assertSame( 1, $o['kpis']['no_show'] );
		// Revenue excludes the cancelled 80: 100 + 50 + 40 = 190.
		$this->assertEqualsWithDelta( 190.0, $o['kpis']['revenue'], 0.001 );
		$this->assertEqualsWithDelta( 100.0, $o['kpis']['collected'], 0.001 );
		$this->assertSame( 25.0, $o['kpis']['cancellation_rate'] );

		// Two revenue days (10th and 11th — 11th has the no_show=40, cancelled excluded).
		$this->assertCount( 2, $o['revenue_by_day'] );
		$this->assertArrayHasKey( 'completed', $o['by_status'] );
		$this->assertSame( 4, array_sum( $o['by_status'] ) );
		$this->assertSame( 1, count( $o['by_staff'] ) );
	}

	public function test_range_filter_excludes_outside(): void {
		$this->appt( 'completed', 100, 100, '2030-06-10 09:00:00' );
		$this->appt( 'completed', 999, 999, '2030-01-01 09:00:00' );

		$o = $this->reports->overview( '2030-06-01 00:00:00', '2030-07-01 00:00:00' );
		$this->assertSame( 1, $o['kpis']['total_bookings'] );
		$this->assertEqualsWithDelta( 100.0, $o['kpis']['revenue'], 0.001 );
	}

	public function test_utilization(): void {
		$schema = new Schema();
		$staff  = new StaffRepository( null, $schema );
		$avail  = new AvailabilityRepository( null, $schema );

		$staff_id = $staff->create( array( 'display_name' => 'Ada', 'status' => 'active' ) );
		// Mondays 09:00–17:00 = 480 available minutes per Monday.
		$avail->create( array( 'staff_id' => $staff_id, 'type' => 'working_hours', 'weekday' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00' ) );
		// One 60-min booking on Monday 2030-06-10.
		$this->appt( 'completed', 100, 100, '2030-06-10 09:00:00', $staff_id );

		$rows = $this->reports->utilization( '2030-06-10', '2030-06-10' );
		$this->assertCount( 1, $rows );
		$this->assertSame( 60, $rows[0]['booked_minutes'] );
		$this->assertSame( 480, $rows[0]['available_minutes'] );
		$this->assertEqualsWithDelta( 12.5, $rows[0]['utilization'], 0.1 );
	}

	public function test_csv_export_has_header_and_rows(): void {
		$this->appt( 'completed', 100, 100, '2030-06-10 09:00:00' );
		$csv = $this->reports->export_csv( 'revenue', '2030-06-01 00:00:00', '2030-07-01 00:00:00' );

		$this->assertStringContainsString( 'Date,Bookings,Revenue', $csv );
		$this->assertStringContainsString( '2030-06-10', $csv );
	}
}
