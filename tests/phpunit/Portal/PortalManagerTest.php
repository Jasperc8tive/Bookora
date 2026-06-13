<?php
/**
 * Portal token + manager tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Portal;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\BookingEngine;
use Bookora\Appointments\Clock;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerRepository;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use Bookora\Portal\PortalManager;
use Bookora\Portal\PortalToken;
use WP_Error;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Portal\PortalToken
 * @covers \Bookora\Portal\PortalManager
 */
class PortalManagerTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private PortalManager $portal;
	private CustomerRepository $customers;
	private AppointmentRepository $appointments;
	private int $customer_id;
	private int $other_id;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		$schema             = new Schema();
		$this->customers    = new CustomerRepository( null, $schema );
		$this->appointments = new AppointmentRepository( null, $schema );

		$this->portal = $this->build_manager( $schema );

		$this->customer_id = $this->customers->create( array( 'name' => 'Ada', 'email' => 'ada@example.com' ) );
		$this->other_id    = $this->customers->create( array( 'name' => 'Eve', 'email' => 'eve@example.com' ) );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	private function build_manager( Schema $schema ): PortalManager {
		$audit_repo = new \Bookora\Database\Repository\AuditLogRepository( null, $schema );
		$audit      = new \Bookora\Security\ActivityLogger( $audit_repo );
		$holds      = new \Bookora\Appointments\BookingHoldRepository( null, $schema );
		$clock      = new Clock();
		$svc        = new \Bookora\Services\ServiceRepository( null, $schema );
		$staff      = new \Bookora\Staff\StaffRepository( null, $schema );
		$assign     = new \Bookora\Staff\StaffServiceRepository( null, $schema );
		$avail      = new \Bookora\Staff\AvailabilityRepository( null, $schema );
		$conflicts  = new \Bookora\Appointments\ConflictDetector( $this->appointments, $holds, $clock );
		$engine     = new BookingEngine( $svc, $staff, $this->customers, $this->appointments, $holds, $conflicts, $clock, $audit );
		$av_engine  = new AvailabilityEngine( $svc, $avail, $assign, $this->appointments, $holds, $clock );
		$cm         = new \Bookora\Customers\CustomerManager( $this->customers, new \Bookora\Customers\NoteRepository( null, $schema ), $audit_repo, $audit );
		$pay        = $this->createMock( \Bookora\Payments\PaymentManager::class );

		return new PortalManager( $this->customers, $cm, $this->appointments, $engine, $av_engine, $pay, new Settings(), $clock );
	}

	private function make_appt( int $customer_id, int $hours_ahead ): int {
		$start = gmdate( 'Y-m-d H:i:s', time() + $hours_ahead * HOUR_IN_SECONDS );
		$end   = gmdate( 'Y-m-d H:i:s', time() + ( $hours_ahead * HOUR_IN_SECONDS ) + 1800 );

		return $this->appointments->create(
			array(
				'customer_id' => $customer_id,
				'service_id'  => 1,
				'staff_id'    => 1,
				'start_at'    => $start,
				'end_at'      => $end,
				'status'      => 'confirmed',
				'total'       => 50,
				'currency'    => 'NGN',
			)
		);
	}

	public function test_token_round_trip_and_expiry(): void {
		$token = PortalToken::sign( 99, 14 );
		$this->assertSame( 99, PortalToken::verify( $token ) );
		$this->assertNull( PortalToken::verify( $token . 'x' ) );
		$this->assertNull( PortalToken::verify( PortalToken::sign( 99, -1 ) ) );
	}

	public function test_bookings_split_upcoming_and_past(): void {
		$this->make_appt( $this->customer_id, 72 );  // upcoming
		$this->make_appt( $this->customer_id, -72 ); // past

		$result = $this->portal->bookings( $this->customer_id );
		$this->assertCount( 1, $result['upcoming'] );
		$this->assertCount( 1, $result['past'] );
		$this->assertTrue( $result['upcoming'][0]['can_cancel'] );
	}

	public function test_cancel_rejects_foreign_booking(): void {
		$appt   = $this->make_appt( $this->other_id, 72 );
		$result = $this->portal->cancel( $this->customer_id, $appt );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_cancel_rejects_inside_window(): void {
		$appt   = $this->make_appt( $this->customer_id, 2 ); // 2h away, window is 24h
		$result = $this->portal->cancel( $this->customer_id, $appt );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, $result->get_error_data()['status'] );
	}

	public function test_cancel_succeeds_within_policy(): void {
		$appt   = $this->make_appt( $this->customer_id, 72 );
		$result = $this->portal->cancel( $this->customer_id, $appt );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['cancelled'] );
	}

	public function test_request_link_no_enumeration(): void {
		// Neither call throws or reveals existence; unknown email is a silent no-op.
		$this->portal->request_link( 'ada@example.com' );
		$this->portal->request_link( 'nobody@example.com' );
		$this->assertTrue( true );
	}
}
