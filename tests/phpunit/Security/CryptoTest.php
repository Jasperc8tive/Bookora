<?php
/**
 * Crypto (AES-256-GCM at-rest encryption) tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Security;

use Bookora\Security\Crypto;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Security\Crypto
 */
class CryptoTest extends WP_UnitTestCase {

	private Crypto $crypto;

	public function set_up(): void {
		parent::set_up();
		$this->crypto = new Crypto();
	}

	public function test_round_trip(): void {
		$secret = 'oauth-token-😀-' . wp_generate_password( 40 );
		$cipher = $this->crypto->encrypt( $secret );

		$this->assertSame( $secret, $this->crypto->decrypt( $cipher ) );
	}

	/**
	 * HOTFIX-005: ciphertext is versioned and is NEVER stored as plaintext.
	 */
	public function test_output_is_versioned_ciphertext_not_plaintext(): void {
		$cipher = $this->crypto->encrypt( 'super-secret-value' );

		$this->assertStringStartsWith( 'enc:v1:', $cipher );
		$this->assertStringNotContainsString( 'super-secret-value', $cipher );
		$this->assertStringNotContainsString( 'plain:', $cipher );
	}

	/**
	 * HOTFIX-005 migration: legacy `plain:` values from pre-1.0 builds still
	 * decrypt so stored secrets are not orphaned on upgrade.
	 */
	public function test_decrypts_legacy_plain_value(): void {
		$legacy = 'plain:' . base64_encode( 'legacy-secret' );
		$this->assertSame( 'legacy-secret', $this->crypto->decrypt( $legacy ) );
	}

	/**
	 * HOTFIX-005 migration: legacy unversioned `enc:` ciphertext still decrypts.
	 */
	public function test_decrypts_legacy_unversioned_ciphertext(): void {
		$cipher = $this->crypto->encrypt( 'value' );
		$legacy = 'enc:' . substr( $cipher, strlen( 'enc:v1:' ) );

		$this->assertSame( 'value', $this->crypto->decrypt( $legacy ) );
	}

	public function test_garbage_returns_null(): void {
		$this->assertNull( $this->crypto->decrypt( 'not-a-cipher' ) );
		$this->assertNull( $this->crypto->decrypt( 'enc:v1:%%%notbase64%%%' ) );
	}
}
