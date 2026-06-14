<?php
/**
 * Bookings + availability REST tests.
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
 * @covers \Bookora\API\Controllers\AvailabilityController
 */
class BookingsControllerTest extends WP_UnitTestCase {

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
		$this->staff_id    = $staff->create( array( 'display_name' => 'Ada', 'status' => 'active' ) );
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

		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	private function as_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function json( string $method, string $route, array $body = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( array() !== $body ) {
			$request->set_body( (string) wp_json_encode( $body ) );
			$request->set_header( 'Content-Type', 'application/json' );
		}

		return rest_do_request( $request );
	}

	public function test_subscriber_is_forbidden(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$status = $this->json( 'GET', '/bookora/v1/availability?service_id=' . $this->service_id . '&date=' . self::DATE )->get_status();
		$this->assertContains( $status, array( 401, 403 ) );
	}

	public function test_availability_returns_slots(): void {
		$this->as_admin();
		$response = $this->json( 'GET', '/bookora/v1/availability?service_id=' . $this->service_id . '&date=' . self::DATE );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 16, $response->get_data()['data']['slots'] );
	}

	public function test_create_then_cancel_booking(): void {
		$this->as_admin();

		$created = $this->json(
			'POST',
			'/bookora/v1/bookings',
			array(
				'service_id'  => $this->service_id,
				'staff_id'    => $this->staff_id,
				'customer_id' => $this->customer_id,
				'start'       => self::DATE . ' 09:00:00',
			)
		);
		$this->assertSame( 201, $created->get_status() );
		$id = (int) $created->get_data()['data']['created'][0]['id'];

		$cancel = $this->json( 'POST', "/bookora/v1/bookings/{$id}/cancel" );
		$this->assertTrue( $cancel->get_data()['data']['cancelled'] );
	}

	public function test_create_conflict_returns_409(): void {
		$this->as_admin();
		$payload = array(
			'service_id'  => $this->service_id,
			'staff_id'    => $this->staff_id,
			'customer_id' => $this->customer_id,
			'start'       => self::DATE . ' 10:00:00',
		);
		$this->assertSame( 201, $this->json( 'POST', '/bookora/v1/bookings', $payload )->get_status() );
		$this->assertSame( 409, $this->json( 'POST', '/bookora/v1/bookings', $payload )->get_status() );
	}
}
