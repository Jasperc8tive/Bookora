<?php
/**
 * Public booking REST tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Rest;

use Bookora\Customers\CustomerRepository;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\AvailabilityRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Staff\StaffServiceRepository;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @covers \Bookora\API\Controllers\PublicBookingController
 */
class PublicBookingControllerTest extends WP_UnitTestCase {

	private const DATE = '2030-06-12';

	private MigrationRunner $runner;
	private int $service_id;
	private int $staff_id;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		$schema      = new Schema();
		$services    = new ServiceRepository( null, $schema );
		$staff       = new StaffRepository( null, $schema );
		$assignments = new StaffServiceRepository( null, $schema );
		$avail       = new AvailabilityRepository( null, $schema );

		$this->service_id = $services->create( array( 'name' => 'Massage', 'duration_min' => 60, 'price' => 100, 'currency' => 'NGN', 'status' => 'active' ) );
		$this->staff_id   = $staff->create( array( 'display_name' => 'Ada', 'status' => 'active' ) );
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

		// Logged-out visitor.
		wp_set_current_user( 0 );
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		$this->runner->rollback();
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

	public function test_services_are_public(): void {
		$response = $this->json( 'GET', '/bookora/v1/book/services' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data()['data']['items'] );
	}

	public function test_only_active_services_are_listed(): void {
		( new ServiceRepository( null, new Schema() ) )->create( array( 'name' => 'Hidden', 'status' => 'inactive' ) );
		$items = $this->json( 'GET', '/bookora/v1/book/services' )->get_data()['data']['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 'Massage', $items[0]['name'] );
	}

	public function test_staff_for_service(): void {
		$items = $this->json( 'GET', "/bookora/v1/book/services/{$this->service_id}/staff" )->get_data()['data']['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 'Ada', $items[0]['name'] );
	}

	public function test_availability_is_public(): void {
		$response = $this->json( 'GET', '/bookora/v1/book/availability?service_id=' . $this->service_id . '&date=' . self::DATE );
		$this->assertSame( 200, $response->get_status() );
		// 09:00–17:00 in 60-min steps = 8 slots.
		$this->assertCount( 8, $response->get_data()['data']['slots'] );
	}

	public function test_booking_creates_a_customer_and_appointment(): void {
		$response = $this->json(
			'POST',
			'/bookora/v1/book/appointments',
			array(
				'service_id' => $this->service_id,
				'staff_id'   => $this->staff_id,
				'start'      => self::DATE . ' 09:00:00',
				'customer'   => array( 'name' => 'Joy', 'email' => 'joy@example.com', 'phone' => '0800' ),
			)
		);
		$this->assertSame( 201, $response->get_status() );

		$appt = $response->get_data()['data']['appointment'];
		$this->assertSame( 'pending', $appt['status'] );

		// Customer was created and is reused on a second booking.
		$customers = new CustomerRepository( null, new Schema() );
		$this->assertNotNull( $customers->find_by( array( 'email' => 'joy@example.com' ) ) );
	}

	public function test_honeypot_blocks_spam(): void {
		$response = $this->json(
			'POST',
			'/bookora/v1/book/appointments',
			array(
				'service_id' => $this->service_id,
				'staff_id'   => $this->staff_id,
				'start'      => self::DATE . ' 10:00:00',
				'customer'   => array( 'name' => 'Bot' ),
				'hp'         => 'i-am-a-bot',
			)
		);
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_double_booking_returns_409(): void {
		$payload = array(
			'service_id' => $this->service_id,
			'staff_id'   => $this->staff_id,
			'start'      => self::DATE . ' 11:00:00',
			'customer'   => array( 'name' => 'A', 'email' => 'a@example.com' ),
		);
		$this->assertSame( 201, $this->json( 'POST', '/bookora/v1/book/appointments', $payload )->get_status() );

		$payload['customer'] = array( 'name' => 'B', 'email' => 'b@example.com' );
		$this->assertSame( 409, $this->json( 'POST', '/bookora/v1/book/appointments', $payload )->get_status() );
	}
}
