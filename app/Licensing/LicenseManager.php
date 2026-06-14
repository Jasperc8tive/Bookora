<?php
/**
 * License activation, validation, and tier resolution.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Licensing;

use Bookora\Security\ActivityLogger;
use Bookora\Security\Crypto;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the site's commercial license: activation/deactivation against a
 * remote license server, encrypted key storage, cached status, and the tier
 * that drives {@see FeatureFlags}.
 *
 * The plugin is fully functional **unlicensed** (free tier). A valid license
 * unlocks higher tiers. All network calls fail soft: if the server is
 * unreachable the last known good status is retained until it expires.
 */
final class LicenseManager {

	/**
	 * Non-autoloaded option holding the license record.
	 */
	private const OPTION = 'bookora_license';

	/**
	 * Default re-validation grace window (seconds) when offline.
	 */
	private const GRACE = 7 * DAY_IN_SECONDS;

	/**
	 * Known tiers, lowest to highest.
	 */
	public const TIERS = array( 'free', 'pro', 'agency' );

	private Crypto $crypto;
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param Crypto         $crypto Secret encryption.
	 * @param ActivityLogger $audit  Audit logger.
	 */
	public function __construct( Crypto $crypto, ActivityLogger $audit ) {
		$this->crypto = $crypto;
		$this->audit  = $audit;
	}

	/**
	 * Remote license API endpoint (filterable; empty disables remote checks).
	 *
	 * @return string
	 */
	private function api_url(): string {
		/**
		 * Filter the license server URL. Empty string keeps the site on the free
		 * tier without any outbound calls (useful for self-hosted/air-gapped).
		 *
		 * @param string $url Endpoint URL.
		 */
		return (string) apply_filters( 'bookora_license_api_url', '' );
	}

	/**
	 * Activate a license key for this site.
	 *
	 * @param string $key License key.
	 * @return array<string, mixed>|WP_Error Status array or error.
	 */
	public function activate( string $key ): array|WP_Error {
		$key = trim( $key );
		if ( '' === $key ) {
			return new WP_Error( 'bookora_license_empty', __( 'A license key is required.', 'bookora' ), array( 'status' => 422 ) );
		}

		$response = $this->remote( 'activate', $key );
		if ( $response instanceof WP_Error ) {
			return $response;
		}

		$record = array(
			'key'        => $this->crypto->encrypt( $key ),
			'status'     => (string) ( $response['status'] ?? 'active' ),
			'tier'       => $this->normalise_tier( (string) ( $response['tier'] ?? 'free' ) ),
			'expires_at' => (string) ( $response['expires_at'] ?? '' ),
			'checked_at' => time(),
		);
		$this->store( $record );
		$this->audit->log( 'license.activated', array( 'tier' => $record['tier'] ) );

		return $this->public_status( $record );
	}

	/**
	 * Deactivate the current license (reverts to the free tier).
	 *
	 * @return array<string, mixed>
	 */
	public function deactivate(): array {
		$key = $this->get_key();
		if ( '' !== $key ) {
			$this->remote( 'deactivate', $key );
		}
		delete_option( self::OPTION );
		$this->audit->log( 'license.deactivated', array() );

		return $this->public_status( null );
	}

	/**
	 * Re-validate the stored license against the server (used by the daily cron).
	 *
	 * @return array<string, mixed>
	 */
	public function refresh(): array {
		$key = $this->get_key();
		if ( '' === $key ) {
			return $this->public_status( null );
		}

		$response = $this->remote( 'check', $key );
		$record   = $this->record();
		if ( ! ( $response instanceof WP_Error ) && is_array( $record ) ) {
			$record['status']     = (string) ( $response['status'] ?? $record['status'] );
			$record['tier']       = $this->normalise_tier( (string) ( $response['tier'] ?? $record['tier'] ) );
			$record['expires_at'] = (string) ( $response['expires_at'] ?? $record['expires_at'] );
			$record['checked_at'] = time();
			$this->store( $record );
		}

		return $this->public_status( $this->record() );
	}

	/**
	 * Current license status (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array {
		return $this->public_status( $this->record() );
	}

	/**
	 * Whether the license is currently valid (active and not expired).
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		$record = $this->record();
		if ( ! is_array( $record ) || 'active' !== ( $record['status'] ?? '' ) ) {
			return false;
		}

		$expires = (string) ( $record['expires_at'] ?? '' );
		if ( '' !== $expires && strtotime( $expires ) < time() ) {
			return false;
		}

		// Drop to free if we have been unable to reach the server well past expiry.
		$checked = (int) ( $record['checked_at'] ?? 0 );
		if ( '' === $expires && $checked > 0 && ( time() - $checked ) > ( 365 * DAY_IN_SECONDS + self::GRACE ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The effective tier ('free' when unlicensed/invalid).
	 *
	 * @return string
	 */
	public function tier(): string {
		if ( ! $this->is_valid() ) {
			return 'free';
		}

		return $this->normalise_tier( (string) ( $this->record()['tier'] ?? 'free' ) );
	}

	/**
	 * Decrypted license key, or '' when none is stored.
	 *
	 * @return string
	 */
	public function get_key(): string {
		$record = $this->record();
		if ( ! is_array( $record ) || empty( $record['key'] ) ) {
			return '';
		}

		return (string) ( $this->crypto->decrypt( (string) $record['key'] ) ?? '' );
	}

	/**
	 * Perform a license-server request. Returns the decoded `data` payload.
	 *
	 * @param string $action activate|deactivate|check.
	 * @param string $key    License key.
	 * @return array<string, mixed>|WP_Error
	 */
	private function remote( string $action, string $key ): array|WP_Error {
		$url = $this->api_url();
		if ( '' === $url ) {
			// Default-deny: with no license server the site stays on the FREE tier.
			// Paid tiers require a verifiable response from a configured server.
			return 'deactivate' === $action ? array() : array(
				'status' => 'active',
				'tier'   => 'free',
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'action'  => $action,
					'key'     => $key,
					'site'    => home_url(),
					'version' => defined( 'BOOKORA_VERSION' ) ? BOOKORA_VERSION : '',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'bookora_license_unreachable', __( 'Could not reach the license server. Please try again.', 'bookora' ), array( 'status' => 503 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );
		if ( $code >= 400 || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['message'] ) ? (string) $body['message'] : __( 'The license key was rejected.', 'bookora' );
			return new WP_Error( 'bookora_license_invalid', $message, array( 'status' => 422 ) );
		}

		$data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;

		// When a public key is configured, the server response MUST be signed.
		// This makes tier/expiry claims tamper-evident (MITM / spoofed JSON).
		if ( ! $this->verify_signature( $data, (string) ( $body['signature'] ?? '' ) ) ) {
			return new WP_Error( 'bookora_license_untrusted', __( 'The license response could not be verified.', 'bookora' ), array( 'status' => 502 ) );
		}

		return $data;
	}

	/**
	 * Verify an RSA-SHA256 signature over the canonical JSON of the response
	 * data, using the configured public key.
	 *
	 * Returns true when no public key is configured (signature enforcement is
	 * opt-in via `bookora_license_public_key`), and false when a key is set but
	 * the signature is missing or invalid.
	 *
	 * @param array<string, mixed> $data      Response data.
	 * @param string               $signature Base64 signature from the server.
	 * @return bool
	 */
	private function verify_signature( array $data, string $signature ): bool {
		/**
		 * Filter the PEM public key used to verify license-server responses.
		 * Empty disables signature enforcement (server is trusted over TLS only).
		 *
		 * @param string $pem PEM-encoded public key.
		 */
		$pem = (string) apply_filters( 'bookora_license_public_key', '' );
		if ( '' === $pem ) {
			return true;
		}
		if ( '' === $signature || ! function_exists( 'openssl_verify' ) ) {
			return false;
		}

		$canonical = (string) wp_json_encode( $data );
		$decoded   = base64_decode( $signature, true );
		if ( false === $decoded ) {
			return false;
		}

		return 1 === openssl_verify( $canonical, $decoded, $pem, OPENSSL_ALGO_SHA256 );
	}

	/**
	 * Build the public (secret-free) status view.
	 *
	 * @param array<string, mixed>|null $record Stored record.
	 * @return array<string, mixed>
	 */
	private function public_status( ?array $record ): array {
		$valid = $this->is_valid();

		return array(
			'licensed'   => is_array( $record ) && ! empty( $record['key'] ),
			'valid'      => $valid,
			'status'     => is_array( $record ) ? (string) ( $record['status'] ?? 'inactive' ) : 'inactive',
			'tier'       => $valid && is_array( $record ) ? $this->normalise_tier( (string) ( $record['tier'] ?? 'free' ) ) : 'free',
			'expires_at' => is_array( $record ) ? (string) ( $record['expires_at'] ?? '' ) : '',
			'masked_key' => $this->mask( $this->get_key() ),
		);
	}

	/**
	 * Mask a key for display (keeps the last 4 characters).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function mask( string $key ): string {
		if ( '' === $key ) {
			return '';
		}
		$tail = substr( $key, -4 );

		return str_repeat( '•', max( 4, strlen( $key ) - 4 ) ) . $tail;
	}

	/**
	 * Coerce an arbitrary tier string to a known tier.
	 *
	 * @param string $tier Candidate tier.
	 * @return string
	 */
	private function normalise_tier( string $tier ): string {
		$tier = strtolower( trim( $tier ) );

		return in_array( $tier, self::TIERS, true ) ? $tier : 'free';
	}

	/**
	 * Raw stored record.
	 *
	 * @return array<string, mixed>|null
	 */
	private function record(): ?array {
		$stored = get_option( self::OPTION, null );

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Persist the license record (not autoloaded — contains a secret).
	 *
	 * @param array<string, mixed> $record Record.
	 * @return void
	 */
	private function store( array $record ): void {
		update_option( self::OPTION, $record, false );
	}
}
