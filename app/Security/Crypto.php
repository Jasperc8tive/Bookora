<?php
/**
 * Symmetric encryption for stored secrets.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-GCM encryption for at-rest secrets (e.g. OAuth tokens).
 *
 * The key is derived from a WordPress salt, so ciphertext is bound to the site.
 * Output is base64( iv(12) . tag(16) . ciphertext ).
 */
final class Crypto {

	private const CIPHER  = 'aes-256-gcm';
	private const IV_LEN  = 12;
	private const TAG_LEN = 16;

	/**
	 * Current ciphertext prefix. Versioned so the on-disk format can evolve and
	 * so decryption can route legacy values during migration.
	 */
	private const PREFIX = 'enc:v1:';

	/**
	 * Encrypt a string with AES-256-GCM.
	 *
	 * There is **no plaintext fallback**: OpenSSL is a hard requirement (enforced
	 * at bootstrap), so secrets are never written unencrypted. A failure throws
	 * rather than silently degrading.
	 *
	 * @param string $plaintext Plaintext.
	 * @return string Versioned, base64 ciphertext (`enc:v1:…`).
	 *
	 * @throws \RuntimeException When OpenSSL is unavailable or encryption fails.
	 */
	public function encrypt( string $plaintext ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			throw new \RuntimeException( 'Bookora requires the OpenSSL PHP extension to store secrets.' );
		}

		$iv  = random_bytes( self::IV_LEN );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN );
		if ( false === $ct ) {
			throw new \RuntimeException( 'Bookora failed to encrypt a secret.' );
		}

		return self::PREFIX . base64_encode( $iv . $tag . $ct );
	}

	/**
	 * Decrypt a value produced by encrypt(). Returns null on any failure —
	 * including after a WordPress salt rotation, which changes the derived key
	 * and renders prior ciphertext undecryptable. Callers treat null as "secret
	 * lost; re-authenticate" (e.g. reconnect a calendar, re-activate a license).
	 *
	 * Reads the current `enc:v1:` format and legacy `enc:` / `plain:` values to
	 * support in-place migration from earlier builds.
	 *
	 * @param string $value Stored value.
	 * @return string|null
	 */
	public function decrypt( string $value ): ?string {
		// Legacy plaintext fallback from pre-1.0 builds (migration only).
		if ( str_starts_with( $value, 'plain:' ) ) {
			$decoded = base64_decode( substr( $value, 6 ), true );

			return false === $decoded ? null : $decoded;
		}

		if ( str_starts_with( $value, self::PREFIX ) ) {
			$payload = substr( $value, strlen( self::PREFIX ) );
		} elseif ( str_starts_with( $value, 'enc:' ) ) {
			$payload = substr( $value, 4 ); // Legacy unversioned ciphertext.
		} else {
			return null;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return null;
		}

		$raw = base64_decode( $payload, true );
		if ( false === $raw || strlen( $raw ) <= self::IV_LEN + self::TAG_LEN ) {
			return null;
		}

		$iv  = substr( $raw, 0, self::IV_LEN );
		$tag = substr( $raw, self::IV_LEN, self::TAG_LEN );
		$ct  = substr( $raw, self::IV_LEN + self::TAG_LEN );

		$plain = openssl_decrypt( $ct, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag );

		return false === $plain ? null : $plain;
	}

	/**
	 * 32-byte key derived from a site salt.
	 *
	 * @return string
	 */
	private function key(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'secure_auth' ) : 'bookora-fallback-salt';

		return hash( 'sha256', 'bookora|' . $salt, true );
	}
}
