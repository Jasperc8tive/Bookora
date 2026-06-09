<?php
/**
 * Staff↔service assignment repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Staff;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `staff_services` join table.
 */
final class StaffServiceRepository extends AbstractRepository {

	/**
	 * The join table is rewritten on assignment; no soft deletes.
	 *
	 * @var bool
	 */
	protected bool $soft_deletes = false;

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'staff_services';
	}

	/**
	 * Service ids assigned to a staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return array<int, int>
	 */
	public function service_ids_for_staff( int $staff_id ): array {
		$table = $this->table();
		$sql   = "SELECT service_id FROM `{$table}` WHERE staff_id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ids = $this->wpdb->get_col( $this->wpdb->prepare( $sql, $staff_id ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Replace the full set of service assignments for a staff member.
	 *
	 * @param int            $staff_id    Staff id.
	 * @param array<int, int> $service_ids Service ids to assign.
	 * @return void
	 */
	public function sync_for_staff( int $staff_id, array $service_ids ): void {
		$this->wpdb->delete( $this->table(), array( 'staff_id' => $staff_id ), array( '%d' ) );

		foreach ( array_values( array_unique( array_map( 'intval', $service_ids ) ) ) as $service_id ) {
			if ( $service_id <= 0 ) {
				continue;
			}
			$this->wpdb->insert(
				$this->table(),
				array(
					'staff_id'   => $staff_id,
					'service_id' => $service_id,
				),
				array( '%d', '%d' )
			);
		}
	}
}
