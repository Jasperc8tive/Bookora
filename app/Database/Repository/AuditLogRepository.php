<?php
/**
 * Append-only audit-log repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Repository for the append-only `activity_logs` table. Soft deletes are
 * disabled — audit records are never updated or trashed.
 */
final class AuditLogRepository extends AbstractRepository {

	/**
	 * Audit rows are immutable.
	 *
	 * @var bool
	 */
	protected bool $soft_deletes = false;

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'activity_logs';
	}

	/**
	 * The most recent entry's after_hash, anchoring the hash chain.
	 *
	 * @return string|null
	 */
	public function last_hash(): ?string {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$hash = $this->wpdb->get_var( "SELECT after_hash FROM `{$table}` ORDER BY id DESC LIMIT 1" );

		return is_string( $hash ) ? $hash : null;
	}

	/**
	 * Audit entries for a specific entity, newest first.
	 *
	 * @param string $entity_type Entity type, e.g. "customer".
	 * @param int    $entity_id   Entity id.
	 * @param int    $limit       Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_entity( string $entity_type, int $entity_id, int $limit = 50 ): array {
		$table = $this->table();
		$sql   = "SELECT id, action, actor_type, actor_id, context, created_at FROM `{$table}`
			WHERE entity_type = %s AND entity_id = %d ORDER BY id DESC LIMIT %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $entity_type, $entity_id, max( 1, $limit ) ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Verify the integrity of the hash chain.
	 *
	 * @return bool True when every row's after_hash matches sha256(prev_hash + payload_hash).
	 */
	public function verify_chain(): bool {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( "SELECT prev_hash, after_hash, context FROM `{$table}` ORDER BY id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return true;
		}

		$previous = '';
		foreach ( $rows as $row ) {
			if ( (string) $row['prev_hash'] !== $previous ) {
				return false;
			}
			$payload  = (string) $row['context'];
			$expected = hash( 'sha256', $previous . $payload );
			if ( $expected !== (string) $row['after_hash'] ) {
				return false;
			}
			$previous = (string) $row['after_hash'];
		}

		return true;
	}
}
