<?php
/**
 * Resource repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Resources;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `resources` (rooms / equipment) table.
 */
final class ResourceRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'resources';
	}

	/**
	 * Count overlapping (non-cancelled) appointments using a resource.
	 *
	 * @param int      $resource_id Resource id.
	 * @param string   $start_utc   Candidate start (UTC).
	 * @param string   $end_utc     Candidate end (UTC).
	 * @param int|null $ignore_id   Appointment id to exclude.
	 * @return int
	 */
	public function overlapping( int $resource_id, string $start_utc, string $end_utc, ?int $ignore_id = null ): int {
		$appts  = $this->schema->table( 'appointments' );
		$sql    = "SELECT COUNT(*) FROM `{$appts}`
			WHERE resource_id = %d AND deleted_at IS NULL
				AND status NOT IN ('cancelled', 'no_show')
				AND start_at < %s AND end_at > %s";
		$params = array( $resource_id, $end_utc, $start_utc );
		if ( null !== $ignore_id ) {
			$sql     .= ' AND id <> %d';
			$params[] = $ignore_id;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $this->wpdb->get_var( $this->wpdb->prepare( $sql, $params ) );
	}
}
