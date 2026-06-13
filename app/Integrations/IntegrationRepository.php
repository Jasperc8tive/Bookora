<?php
/**
 * Integration repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `integrations` table (one row per provider key).
 */
final class IntegrationRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'integrations';
	}

	/**
	 * Find an integration by its provider key.
	 *
	 * @param string $provider Provider key.
	 * @return array<string, mixed>|null
	 */
	public function for_provider( string $provider ): ?array {
		return $this->find_by( array( 'provider' => $provider ) );
	}

	/**
	 * Create or update the row for a provider key.
	 *
	 * @param string               $provider Provider key.
	 * @param array<string, mixed> $data     Columns to set.
	 * @return int Row id.
	 */
	public function upsert( string $provider, array $data ): int {
		$existing = $this->for_provider( $provider );
		if ( null !== $existing ) {
			$this->update( (int) $existing['id'], $data );

			return (int) $existing['id'];
		}

		$data['provider'] = $provider;

		return $this->create( $data );
	}

	/**
	 * Permanently remove a provider's row.
	 *
	 * @param string $provider Provider key.
	 * @return void
	 */
	public function forget( string $provider ): void {
		$existing = $this->for_provider( $provider );
		if ( null !== $existing ) {
			$this->force_delete( (int) $existing['id'] );
		}
	}

	/**
	 * Provider keys matching a SQL LIKE pattern (e.g. "google_%").
	 *
	 * @param string $like LIKE pattern.
	 * @return array<int, array<string, mixed>>
	 */
	public function like_provider( string $like ): array {
		$table = $this->table();
		$sql   = "SELECT * FROM `{$table}` WHERE provider LIKE %s AND deleted_at IS NULL";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $like ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}
}
