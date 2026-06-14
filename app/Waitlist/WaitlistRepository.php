<?php
/**
 * Waitlist repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Waitlist;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `waitlist` table.
 */
final class WaitlistRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'waitlist';
	}

	/**
	 * Waitlist entries (optionally for one service), joined with names.
	 *
	 * @param int $service_id Service id, or 0 for all.
	 * @return array<int, array<string, mixed>>
	 */
	public function listing( int $service_id = 0 ): array {
		$wl        = $this->table();
		$services  = $this->schema->table( 'services' );
		$customers = $this->schema->table( 'customers' );

		$where  = 'w.deleted_at IS NULL';
		$params = array();
		if ( $service_id > 0 ) {
			$where   .= ' AND w.service_id = %d';
			$params[] = $service_id;
		}
		$sql = "SELECT w.*, s.name AS service_name, c.name AS customer_name, c.email AS customer_email
			FROM `{$wl}` w
			LEFT JOIN `{$services}` s ON w.service_id = s.id
			LEFT JOIN `{$customers}` c ON w.customer_id = c.id
			WHERE {$where} ORDER BY w.id ASC";

		if ( array() === $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ), ARRAY_A );
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Active entries for a service.
	 *
	 * @param int $service_id Service id.
	 * @return array<int, array<string, mixed>>
	 */
	public function active_for_service( int $service_id ): array {
		return $this->all(
			array(
				'where'   => array(
					'service_id' => $service_id,
					'status'     => 'active',
				),
				'orderby' => 'id',
				'order'   => 'ASC',
			)
		);
	}
}
