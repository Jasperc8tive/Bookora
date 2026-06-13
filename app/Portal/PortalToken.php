<?php
/**
 * Customer portal magic-link token.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Portal;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and verifies HMAC-signed, time-limited customer portal tokens.
 *
 * The token is a stateless bearer: it embeds the customer id + expiry and is
 * signed with a site salt, so portal endpoints resolve the customer without a
 * server session. Tokens are single-purpose (portal access only).
 */
final class PortalToken {

	/**
	 * Sign a portal token for a customer.
	 *
	 * @param int $customer_id Customer id.
	 * @param int $ttl_days    Lifetime in days.
	 * @return string URL-safe token.
	 */
	public static function sign( int $customer_id, int $ttl_days = 14 ): string {
		$expires = time() + ( max( 1, $ttl_days ) * DAY_IN_SECONDS );
		$payload = $customer_id . '|' . $expires;
		$sig     = hash_hmac( 'sha256', $payload, self::secret() );

		return rtrim( strtr( base64_encode( $payload . '|' . $sig ), '+/', '-_' ), '=' );
	}

	/**
	 * Verify a token and return the customer id, or null when invalid/expired.
	 *
	 * @param string $token Token value.
	 * @return int|null
	 */
	public static function verify( string $token ): ?int {
		$decoded = base64_decode( strtr( $token, '-_', '+/' ), true );
		if ( false === $decoded ) {
			return null;
		}
		$parts = explode( '|', $decoded );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		list( $customer_id, $expires, $sig ) = $parts;

		$expected = hash_hmac( 'sha256', $customer_id . '|' . $expires, self::secret() );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}
		if ( time() > (int) $expires ) {
			return null;
		}

		return (int) $customer_id;
	}

	/**
	 * Signing secret, bound to the site.
	 *
	 * @return string
	 */
	private static function secret(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'bookora-portal';

		return hash( 'sha256', 'bookora-portal|' . $salt );
	}
}
