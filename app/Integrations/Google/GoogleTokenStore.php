<?php
/**
 * Per-staff Google OAuth token store.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations\Google;

use Bookora\Integrations\IntegrationRepository;
use Bookora\Security\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Stores each staff member's encrypted Google OAuth tokens in the integrations
 * table under the provider key "google_{staff_id}".
 */
final class GoogleTokenStore {

	private IntegrationRepository $repository;
	private Crypto $crypto;

	/**
	 * Constructor.
	 *
	 * @param IntegrationRepository $repository Integration repository.
	 * @param Crypto                $crypto     Encryptor.
	 */
	public function __construct( IntegrationRepository $repository, Crypto $crypto ) {
		$this->repository = $repository;
		$this->crypto     = $crypto;
	}

	/**
	 * Provider key for a staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return string
	 */
	public function provider_key( int $staff_id ): string {
		return 'google_' . $staff_id;
	}

	/**
	 * Persist tokens for a staff member.
	 *
	 * @param int                  $staff_id Staff id.
	 * @param array<string, mixed> $tokens   Token set (access_token, refresh_token, expires_in, scope, calendar_id).
	 * @return void
	 */
	public function store( int $staff_id, array $tokens ): void {
		$expires_in = (int) ( $tokens['expires_in'] ?? 3600 );
		$this->repository->upsert(
			$this->provider_key( $staff_id ),
			array(
				'name'        => 'Google Calendar',
				'status'      => 'connected',
				'scope'       => mb_substr( (string) ( $tokens['scope'] ?? '' ), 0, 100 ),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $expires_in ),
				'config'      => (string) wp_json_encode( array( 'calendar_id' => (string) ( $tokens['calendar_id'] ?? 'primary' ) ) ),
				'credentials' => $this->crypto->encrypt( (string) wp_json_encode( $tokens ) ),
			)
		);
	}

	/**
	 * Decrypted tokens for a staff member, or null if not connected.
	 *
	 * @param int $staff_id Staff id.
	 * @return array<string, mixed>|null
	 */
	public function get( int $staff_id ): ?array {
		$row = $this->repository->for_provider( $this->provider_key( $staff_id ) );
		if ( null === $row || 'connected' !== $row['status'] || empty( $row['credentials'] ) ) {
			return null;
		}
		$json = $this->crypto->decrypt( (string) $row['credentials'] );
		if ( null === $json ) {
			return null;
		}
		$tokens = json_decode( $json, true );

		return is_array( $tokens ) ? $tokens : null;
	}

	/**
	 * Update just the access token + expiry after a refresh.
	 *
	 * @param int    $staff_id     Staff id.
	 * @param string $access_token New access token.
	 * @param int    $expires_in   Seconds until expiry.
	 * @return void
	 */
	public function update_access( int $staff_id, string $access_token, int $expires_in ): void {
		$tokens = $this->get( $staff_id );
		if ( null === $tokens ) {
			return;
		}
		$tokens['access_token'] = $access_token;
		$tokens['expires_in']   = $expires_in;
		$this->store( $staff_id, $tokens );
	}

	/**
	 * Whether a staff member's stored access token has expired.
	 *
	 * @param int $staff_id Staff id.
	 * @return bool
	 */
	public function is_expired( int $staff_id ): bool {
		$row = $this->repository->for_provider( $this->provider_key( $staff_id ) );
		if ( null === $row || empty( $row['expires_at'] ) ) {
			return true;
		}

		return strtotime( (string) $row['expires_at'] ) <= time() + 60;
	}

	/**
	 * Whether a staff member is connected.
	 *
	 * @param int $staff_id Staff id.
	 * @return bool
	 */
	public function is_connected( int $staff_id ): bool {
		$row = $this->repository->for_provider( $this->provider_key( $staff_id ) );

		return null !== $row && 'connected' === $row['status'];
	}

	/**
	 * Disconnect a staff member (forget tokens).
	 *
	 * @param int $staff_id Staff id.
	 * @return void
	 */
	public function disconnect( int $staff_id ): void {
		$this->repository->forget( $this->provider_key( $staff_id ) );
	}

	/**
	 * Staff ids with a connected Google account.
	 *
	 * @return array<int, int>
	 */
	public function connected_staff_ids(): array {
		$ids = array();
		foreach ( $this->repository->like_provider( 'google_%' ) as $row ) {
			if ( 'connected' === $row['status'] ) {
				$ids[] = (int) substr( (string) $row['provider'], strlen( 'google_' ) );
			}
		}

		return $ids;
	}
}
