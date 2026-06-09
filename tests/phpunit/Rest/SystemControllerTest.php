<?php
/**
 * System REST controller tests.
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
 * @covers \Bookora\API\Controllers\SystemController
 * @covers \Bookora\API\AbstractController
 */
class SystemControllerTest extends WP_UnitTestCase {

	private MigrationRunner $runner;

	public function set_up(): void {
		parent::set_up();

		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		// Grant the administrator the bookora_manage_settings capability.
		( new Roles() )->install();

		// Ensure the REST routes are registered against the test server.
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	public function test_health_returns_200_for_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/bookora/v1/system/health' ) );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( BOOKORA_VERSION, $data['data']['plugin']['version'] );
		$this->assertTrue( $data['data']['database']['migrated'] );
		$this->assertArrayHasKey( 'appointments', $data['data']['database']['tables'] );
	}

	public function test_health_is_forbidden_for_anonymous(): void {
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/bookora/v1/system/health' ) );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	public function test_health_is_forbidden_for_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/bookora/v1/system/health' ) );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}
}
