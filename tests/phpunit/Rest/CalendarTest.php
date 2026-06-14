<?php
/**
 * Calendar feed + resize reschedule tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Rest;

use Bookora\Customers\CustomerRepository;
use Bookora\Database\Schema;
use Bookora\Security\Roles;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\AvailabilityRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Staff\StaffServiceRepository;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @covers \Bookora\API\Controllers\BookingsController
 * @covers \Bookora\Appointments\AppointmentRepository
 */
class CalendarTest extends WP_UnitTestCase {

	private const DATE = '2030-06-12';

	private int $service_id;
	private int $staff_id;
	private int $customer_id;

	public function set_up(): void {
		parent::set_up();
		( new Roles() )->install();

		$schema      = new Schema();
		$services    = new ServiceRepository( null, $schema );
		$staff       = new StaffRepository( null, $schema );
		$assignments = new StaffServiceRepository( null, $schema );
		$avail       = new AvailabilityRepository( null, $schema );
		$customers   = new CustomerRepository( null, $schema );

		$this->service_id  = $services->create( array( 'name' => 'Cut', 'duration_min' => 30, 'price' => 20, 'currency' => 'NGN', 'status' => 'active' ) );
		$this->staff_id    = $staff->create( array( 'display_name' => 'Ada', 'status' => 'active', 'color' => '#123456' ) );
		$this->customer_id = $customers->create( array( 'name' => 'Joy' ) );
		$assignments->sync_for_staff( $this->staff_id, array( $this->service_id ) );
		$avail->create(
			array(
				'staff_id'   => $this->staff_id,
				'type'       => 'working_hours',
				'weekday'    => (int) gmdate( 'w', (int) strtotime( self::DATE ) ),
				'start_time' => '09:00:00',
				'end_time'   => '17:00:00',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	private function json( string $method, string $route, array $body = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( array() !== $body ) {
			$request->set_body( (string) wp_json_encode( $body ) );
			$request->set_header( 'Content-Type', 'application/json' );
		}

		return rest_do_request( $request );
	}

	private function book( string $start ): int {
		$created = $this->json(
			'POST',
			'/bookora/v1/bookings',
			array(
				'service_id'  => $this->service_id,
				'staff_id'    => $this->staff_id,
				'customer_id' => $this->customer_id,
				'start'       => $start,
			)
		);

		return (int) $created->get_data()['data']['created'][0]['id'];
	}

	public function test_calendar_feed_returns_events_with_join_data(): void {
		$this->book( self::DATE . ' 09:00:00' );

		$response = $this->json( 'GET', '/bookora/v1/bookings/calendar?from=' . self::DATE . '&to=2030-06-13' );
		$this->assertSame( 200, $response->get_status() );

		$events = $response->get_data()['data']['events'];
		$this->assertCount( 1, $events );
		$this->assertStringContainsString( 'Cut', $events[0]['title'] );
		$this->assertStringContainsString( 'Joy', $events[0]['title'] );
		$this->assertSame( '#123456', $events[0]['backgroundColor'] );
		$this->assertSame( self::DATE . 'T09:00:00', $events[0]['start'] );
	}

	public function test_calendar_requires_valid_range(): void {
		$this->assertSame( 422, $this->json( 'GET', '/bookora/v1/bookings/calendar?from=bad&to=also-bad' )->get_status() );
	}

	public function test_resize_changes_end_time(): void {
		$id = $this->book( self::DATE . ' 09:00:00' );

		$response = $this->json(
			'PATCH',
			"/bookora/v1/bookings/{$id}",
			array(
				'start' => self::DATE . ' 09:00:00',
				'end'   => self::DATE . ' 10:00:00',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::DATE . ' 10:00:00', $response->get_data()['data']['end_at'] );
	}

	public function test_resize_rejects_end_before_start(): void {
		$id = $this->book( self::DATE . ' 11:00:00' );

		$response = $this->json(
			'PATCH',
			"/bookora/v1/bookings/{$id}",
			array(
				'start' => self::DATE . ' 11:00:00',
				'end'   => self::DATE . ' 10:00:00',
			)
		);
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_drag_reschedule_to_taken_slot_conflicts(): void {
		$this->book( self::DATE . ' 09:00:00' );
		$second = $this->book( self::DATE . ' 12:00:00' );

		// Drag the second onto the first.
		$response = $this->json( 'PATCH', "/bookora/v1/bookings/{$second}", array( 'start' => self::DATE . ' 09:00:00' ) );
		$this->assertSame( 409, $response->get_status() );
	}
}
