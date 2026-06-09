<?php
/**
 * Staff REST controller tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Rest;

use Bookora\Database\MigrationRunner;
use Bookora\Security\Roles;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @covers \Bookora\API\Controllers\StaffController
 * @covers \Bookora\API\Controllers\StaffAvailabilityController
 */
class StaffControllerTest extends WP_UnitTestCase {

	private MigrationRunner $runner;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();
		( new Roles() )->install();
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		$this->runner->rollback();
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
		$this->assertContains( $this->json( 'GET', '/bookora/v1/staff' )->get_status(), array( 401, 403 ) );
	}

	public function test_create_with_relations_and_fetch(): void {
		$this->as_admin();

		$created = $this->json(
			'POST',
			'/bookora/v1/staff',
			array( 'display_name' => 'Ada', 'skills' => 'Massage, Facials', 'service_ids' => array( 2, 4 ) )
		);
		$this->assertSame( 201, $created->get_status() );

		$id   = (int) $created->get_data()['data']['id'];
		$show = $this->json( 'GET', "/bookora/v1/staff/{$id}" );

		$data = $show->get_data()['data'];
		$this->assertSame( array( 'Massage', 'Facials' ), $data['skills'] );
		$this->assertEqualsCanonicalizing( array( 2, 4 ), $data['service_ids'] );
	}

	public function test_availability_round_trip(): void {
		$this->as_admin();

		$id = (int) $this->json( 'POST', '/bookora/v1/staff', array( 'display_name' => 'Joy' ) )->get_data()['data']['id'];

		$save = $this->json(
			'PUT',
			"/bookora/v1/staff/{$id}/availability",
			array( 'working_hours' => array( array( 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00' ) ) )
		);
		$this->assertSame( 200, $save->get_status() );

		$get = $this->json( 'GET', "/bookora/v1/staff/{$id}/availability" );
		$this->assertCount( 1, $get->get_data()['data']['working_hours'] );
	}

	public function test_validation_error_returns_422(): void {
		$this->as_admin();
		$this->assertSame( 422, $this->json( 'POST', '/bookora/v1/staff', array( 'email' => 'bad' ) )->get_status() );
	}
}
