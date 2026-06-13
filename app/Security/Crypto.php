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
	 * Encrypt a string. Returns the original on environments without OpenSSL
	 * (degraded, but functional) — callers should treat stored values as opaque.
	 *
	 * @param string $plaintext Plaintext.
	 * @return string Base64 ciphertext (prefixed) or a marker for plaintext fallback.
	 */
	public function encrypt( string $plaintext ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'plain:' . base64_encode( $plaintext );
		}

		$iv  = random_bytes( self::IV_LEN );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN );
		if ( false === $ct ) {
			return 'plain:' . base64_encode( $plaintext );
		}

		return 'enc:' . base64_encode( $iv . $tag . $ct );
	}

	/**
	 * Decrypt a value produced by encrypt(). Returns null on failure.
	 *
	 * @param string $value Stored value.
	 * @return string|null
	 */
	public function decrypt( string $value ): ?string {
		if ( str_starts_with( $value, 'plain:' ) ) {
			$decoded = base64_decode( substr( $value, 6 ), true );

			return false === $decoded ? null : $decoded;
		}
		if ( ! str_starts_with( $value, 'enc:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return null;
		}

		$raw = base64_decode( substr( $value, 4 ), true );
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
