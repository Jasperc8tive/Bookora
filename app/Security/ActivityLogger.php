<?php
/**
 * Tamper-evident activity (audit) logger.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

use Bookora\Database\Repository\AuditLogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Writes append-only, hash-chained audit records.
 *
 * Each row's `after_hash` = sha256(previous after_hash + canonical payload),
 * making any insertion/edit/deletion of history detectable. IP and user-agent
 * are HMAC-hashed — never stored in the clear — so the log holds no raw PII.
 */
final class ActivityLogger {

	/**
	 * Audit repository.
	 *
	 * @var AuditLogRepository
	 */
	private AuditLogRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param AuditLogRepository $repository Audit repository.
	 */
	public function __construct( AuditLogRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Record an auditable action.
	 *
	 * @param string $action Action key, e.g. "settings.changed".
	 * @param array{
	 *     entity_type?: string|null,
	 *     entity_id?: int|null,
	 *     actor_type?: string|null,
	 *     actor_id?: int|null,
	 *     tenant_id?: int,
	 *     context?: array<string, scalar|null>
	 * } $args Optional metadata.
	 * @return int Inserted row id.
	 */
	public function log( string $action, array $args = array() ): int {
		$user_id    = get_current_user_id();
		$actor_type = $args['actor_type'] ?? ( $user_id > 0 ? 'user' : 'system' );
		$actor_id   = $args['actor_id'] ?? ( $user_id > 0 ? $user_id : null );

		$ip_hash = $this->hash( $this->client_ip() );
		$ua_hash = $this->hash( $this->user_agent() );

		$created_at = current_time( 'mysql', true );
		$prev_hash  = (string) ( $this->repository->last_hash() ?? '' );

		$payload = array(
			'action'      => $action,
			'actor_id'    => $actor_id,
			'actor_type'  => $actor_type,
			'context'     => $args['context'] ?? array(),
			'created_at'  => $created_at,
			'entity_id'   => $args['entity_id'] ?? null,
			'entity_type' => $args['entity_type'] ?? null,
			'ip_hash'     => $ip_hash,
			'tenant_id'   => (int) ( $args['tenant_id'] ?? 0 ),
			'ua_hash'     => $ua_hash,
		);
		ksort( $payload );

		$payload_json = (string) wp_json_encode( $payload );
		$after_hash   = hash( 'sha256', $prev_hash . $payload_json );

		return $this->repository->create(
			array(
				'tenant_id'   => (int) ( $args['tenant_id'] ?? 0 ),
				'actor_type'  => $actor_type,
				'actor_id'    => $actor_id,
				'action'      => $action,
				'entity_type' => $args['entity_type'] ?? null,
				'entity_id'   => $args['entity_id'] ?? null,
				'ip_hash'     => $ip_hash,
				'ua_hash'     => $ua_hash,
				'prev_hash'   => $prev_hash,
				'after_hash'  => $after_hash,
				'context'     => $payload_json,
				'created_at'  => $created_at,
			)
		);
	}

	/**
	 * HMAC a value with a per-site secret. Empty input hashes to an empty string.
	 *
	 * @param string $value Value to hash.
	 * @return string
	 */
	private function hash( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		return hash_hmac( 'sha256', $value, $this->secret() );
	}

	/**
	 * Per-site audit secret, generated once and stored.
	 *
	 * @return string
	 */
	private function secret(): string {
		$secret = get_option( 'bookora_audit_secret' );
		if ( ! is_string( $secret ) || '' === $secret ) {
			$secret = wp_generate_password( 64, false, false );
			update_option( 'bookora_audit_secret', $secret, false );
		}

		return $secret;
	}

	/**
	 * Best-effort client IP from the request.
	 *
	 * @return string
	 */
	private function client_ip(): string {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	/**
	 * Best-effort user agent from the request.
	 *
	 * @return string
	 */
	private function user_agent(): string {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}
}
