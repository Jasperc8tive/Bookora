<?php
/**
 * Signed OAuth state helper.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and verifies HMAC-signed OAuth `state` values binding a staff id and
 * a timestamp. Shared by the Google and Microsoft OAuth flows (CSRF defence).
 */
final class OAuthState {

	/**
	 * Maximum age of a state value, in seconds.
	 */
	private const MAX_AGE = 900;

	/**
	 * Sign a state for a staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return string URL-safe base64 state.
	 */
	public static function sign( int $staff_id ): string {
		$payload = $staff_id . '|' . time();
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );

		return rtrim( strtr( base64_encode( $payload . '|' . $sig ), '+/', '-_' ), '=' );
	}

	/**
	 * Verify a state value and return the staff id, or null when invalid/expired.
	 *
	 * @param string $state State value.
	 * @return int|null
	 */
	public static function verify( string $state ): ?int {
		$decoded = base64_decode( strtr( $state, '-_', '+/' ), true );
		if ( false === $decoded ) {
			return null;
		}
		$parts = explode( '|', $decoded );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		list( $staff_id, $ts, $sig ) = $parts;

		$expected = hash_hmac( 'sha256', $staff_id . '|' . $ts, wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}
		if ( time() - (int) $ts > self::MAX_AGE ) {
			return null;
		}

		return (int) $staff_id;
	}
}
