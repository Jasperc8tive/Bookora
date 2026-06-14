<?php
/**
 * Services REST controller tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Rest;

use Bookora\Security\Roles;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @covers \Bookora\API\Controllers\ServicesController
 * @covers \Bookora\API\Router
 */
class ServicesControllerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		( new Roles() )->install();
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	private function as_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_subscriber_is_forbidden(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/bookora/v1/services' ) );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	public function test_create_then_list(): void {
		$this->as_admin();

		$create = new WP_REST_Request( 'POST', '/bookora/v1/services' );
		$create->set_body_params( array() );
		$create->set_body( (string) wp_json_encode( array( 'name' => 'Swedish Massage', 'price' => 40, 'duration_min' => 45 ) ) );
		$create->set_header( 'Content-Type', 'application/json' );

		$created = rest_do_request( $create );
		$this->assertSame( 201, $created->get_status() );
		$this->assertSame( 'Swedish Massage', $created->get_data()['data']['name'] );

		$list = rest_do_request( new WP_REST_Request( 'GET', '/bookora/v1/services' ) );
		$this->assertSame( 200, $list->get_status() );
		$this->assertSame( 1, $list->get_data()['data']['total'] );
	}

	public function test_validation_error_returns_422(): void {
		$this->as_admin();

		$create = new WP_REST_Request( 'POST', '/bookora/v1/services' );
		$create->set_body( (string) wp_json_encode( array( 'duration_min' => 0 ) ) );
		$create->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $create );
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_bulk_action(): void {
		$this->as_admin();

		foreach ( array( 'One', 'Two' ) as $name ) {
			$req = new WP_REST_Request( 'POST', '/bookora/v1/services' );
			$req->set_body( (string) wp_json_encode( array( 'name' => $name, 'status' => 'inactive' ) ) );
			$req->set_header( 'Content-Type', 'application/json' );
			rest_do_request( $req );
		}

		$list = rest_do_request( new WP_REST_Request( 'GET', '/bookora/v1/services' ) );
		$ids  = wp_list_pluck( $list->get_data()['data']['items'], 'id' );

		$bulk = new WP_REST_Request( 'POST', '/bookora/v1/services/bulk' );
		$bulk->set_body( (string) wp_json_encode( array( 'action' => 'activate', 'ids' => $ids ) ) );
		$bulk->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $bulk );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_data()['data']['affected'] );
	}
}
