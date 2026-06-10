<?php
/**
 * Note repository (polymorphic).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Customers;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the polymorphic `notes` table.
 */
final class NoteRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'notes';
	}

	/**
	 * Notes attached to an entity, newest first.
	 *
	 * @param string $entity_type Entity type, e.g. "customer".
	 * @param int    $entity_id   Entity id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_entity( string $entity_type, int $entity_id ): array {
		$table = $this->table();
		$sql   = "SELECT * FROM `{$table}`
			WHERE entity_type = %s AND entity_id = %d AND deleted_at IS NULL
			ORDER BY id DESC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $entity_type, $entity_id ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}
}
