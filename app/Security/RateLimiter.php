<?php
/**
 * Fixed-window rate limiter.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Transient-backed fixed-window rate limiter.
 *
 * Transients persist across requests (and use the object cache when one is
 * available), so this works on commodity shared hosting without Redis.
 */
final class RateLimiter {

	/**
	 * Transient key prefix.
	 */
	private const PREFIX = 'bookora_rl_';

	/**
	 * Register one hit against a key and report the resulting state.
	 *
	 * @param string $key     Logical bucket key (e.g. "rest:1.2.3.4").
	 * @param int    $max     Maximum hits allowed within the window.
	 * @param int    $window  Window length in seconds.
	 * @return array{allowed: bool, remaining: int, retry_after: int, limit: int}
	 */
	public function hit( string $key, int $max, int $window ): array {
		$max    = max( 1, $max );
		$window = max( 1, $window );
		$tkey   = $this->transient_key( $key );
		$now    = time();

		$data = get_transient( $tkey );
		if ( ! is_array( $data ) || ! isset( $data['reset'], $data['count'] ) || $now >= (int) $data['reset'] ) {
			$data = array(
				'count' => 0,
				'reset' => $now + $window,
			);
		}

		++$data['count'];
		$ttl = max( 1, (int) $data['reset'] - $now );
		set_transient( $tkey, $data, $ttl );

		$allowed = $data['count'] <= $max;

		return array(
			'allowed'     => $allowed,
			'remaining'   => max( 0, $max - (int) $data['count'] ),
			'retry_after' => $allowed ? 0 : ( (int) $data['reset'] - $now ),
			'limit'       => $max,
		);
	}

	/**
	 * Whether the key has already exceeded the limit (without registering a hit).
	 *
	 * @param string $key Logical bucket key.
	 * @param int    $max Maximum hits allowed.
	 * @return bool
	 */
	public function too_many( string $key, int $max ): bool {
		$data = get_transient( $this->transient_key( $key ) );
		if ( ! is_array( $data ) || ! isset( $data['count'], $data['reset'] ) ) {
			return false;
		}
		if ( time() >= (int) $data['reset'] ) {
			return false;
		}

		return (int) $data['count'] > max( 1, $max );
	}

	/**
	 * Reset a key's counter.
	 *
	 * @param string $key Logical bucket key.
	 * @return void
	 */
	public function clear( string $key ): void {
		delete_transient( $this->transient_key( $key ) );
	}

	/**
	 * Hash a logical key into a safe, length-bounded transient name.
	 *
	 * @param string $key Logical bucket key.
	 * @return string
	 */
	private function transient_key( string $key ): string {
		return self::PREFIX . md5( $key );
	}
}
