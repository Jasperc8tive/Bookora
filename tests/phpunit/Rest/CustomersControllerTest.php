<?php
/**
 * Customers REST controller tests.
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
 * @covers \Bookora\API\Controllers\CustomersController
 * @covers \Bookora\API\Controllers\CustomerNotesController
 */
class CustomersControllerTest extends WP_UnitTestCase {

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
		$this->assertContains( $this->json( 'GET', '/bookora/v1/customers' )->get_status(), array( 401, 403 ) );
	}

	public function test_create_show_with_tags(): void {
		$this->as_admin();
		$created = $this->json( 'POST', '/bookora/v1/customers', array( 'name' => 'Ada', 'tags' => 'VIP, Regular' ) );
		$this->assertSame( 201, $created->get_status() );

		$id   = (int) $created->get_data()['data']['id'];
		$show = $this->json( 'GET', "/bookora/v1/customers/{$id}" );
		$this->assertSame( array( 'VIP', 'Regular' ), $show->get_data()['data']['tags'] );
	}

	public function test_notes_round_trip(): void {
		$this->as_admin();
		$id = (int) $this->json( 'POST', '/bookora/v1/customers', array( 'name' => 'Ada' ) )->get_data()['data']['id'];

		$note = $this->json( 'POST', "/bookora/v1/customers/{$id}/notes", array( 'body' => 'VIP client' ) );
		$this->assertSame( 201, $note->get_status() );

		$list = $this->json( 'GET', "/bookora/v1/customers/{$id}/notes" );
		$this->assertCount( 1, $list->get_data()['data']['items'] );
	}

	public function test_timeline_and_bookings_endpoints(): void {
		$this->as_admin();
		$id = (int) $this->json( 'POST', '/bookora/v1/customers', array( 'name' => 'Ada' ) )->get_data()['data']['id'];

		$this->assertSame( 200, $this->json( 'GET', "/bookora/v1/customers/{$id}/timeline" )->get_status() );
		$this->assertSame( 200, $this->json( 'GET', "/bookora/v1/customers/{$id}/bookings" )->get_status() );
	}

	public function test_duplicate_email_returns_422(): void {
		$this->as_admin();
		$this->json( 'POST', '/bookora/v1/customers', array( 'name' => 'Ada', 'email' => 'ada@example.com' ) );
		$dup = $this->json( 'POST', '/bookora/v1/customers', array( 'name' => 'Ada2', 'email' => 'ada@example.com' ) );
		$this->assertSame( 422, $dup->get_status() );
	}
}
