<?php
/**
 * Staff-availability repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Staff;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for `staff_availability` (working hours, breaks, time off, holidays).
 */
final class AvailabilityRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'staff_availability';
	}

	/**
	 * All availability entries for a staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_staff( int $staff_id ): array {
		$table = $this->table();
		$sql   = "SELECT * FROM `{$table}` WHERE staff_id = %d AND deleted_at IS NULL ORDER BY type ASC, weekday ASC, start_time ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $staff_id ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Permanently delete all availability entries for a staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return void
	 */
	public function clear_for_staff( int $staff_id ): void {
		$this->wpdb->delete( $this->table(), array( 'staff_id' => $staff_id ), array( '%d' ) );
	}
}
