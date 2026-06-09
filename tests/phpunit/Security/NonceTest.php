<?php
/**
 * Nonce helper tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Security;

use Bookora\Security\Nonce;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Security\Nonce
 */
class NonceTest extends WP_UnitTestCase {

	public function test_create_and_verify_round_trip(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$nonce = new Nonce();
		$token = $nonce->create( 'save_service' );

		$this->assertTrue( $nonce->verify( $token, 'save_service' ) );
	}

	public function test_verify_fails_for_wrong_action(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$nonce = new Nonce();
		$token = $nonce->create( 'save_service' );

		$this->assertFalse( $nonce->verify( $token, 'delete_service' ) );
	}

	public function test_verify_fails_for_garbage(): void {
		$nonce = new Nonce();
		$this->assertFalse( $nonce->verify( 'not-a-real-nonce', 'save_service' ) );
	}
}
