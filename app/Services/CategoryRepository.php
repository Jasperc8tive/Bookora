<?php
/**
 * Service-category repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Services;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `service_categories` table.
 */
final class CategoryRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'service_categories';
	}

	/**
	 * List categories ordered by sort order then name.
	 *
	 * @param bool $include_trashed Include soft-deleted rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_ordered( bool $include_trashed = false ): array {
		$table = $this->table();
		$where = $include_trashed ? '' : 'WHERE deleted_at IS NULL';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( "SELECT * FROM `{$table}` {$where} ORDER BY sort_order ASC, name ASC", ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}
}
