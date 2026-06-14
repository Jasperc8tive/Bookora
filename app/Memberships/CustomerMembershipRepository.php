<?php
/**
 * Customer↔membership repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Memberships;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `customer_memberships` join (also backs subscriptions).
 */
final class CustomerMembershipRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'customer_memberships';
	}

	/**
	 * The customer's active membership joined with its plan benefit, or null.
	 *
	 * @param int $customer_id Customer id.
	 * @return array<string, mixed>|null
	 */
	public function active_for_customer( int $customer_id ): ?array {
		$cm    = $this->table();
		$plans = $this->schema->table( 'memberships' );
		$sql   = "SELECT cm.*, m.name AS plan_name, m.benefit_type, m.benefit_value
			FROM `{$cm}` cm INNER JOIN `{$plans}` m ON cm.membership_id = m.id
			WHERE cm.customer_id = %d AND cm.status = 'active' AND cm.deleted_at IS NULL
			ORDER BY cm.id DESC LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $customer_id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Memberships due for renewal at or before a cutoff.
	 *
	 * @param string $cutoff_utc Cutoff datetime (UTC).
	 * @return array<int, array<string, mixed>>
	 */
	public function due_for_renewal( string $cutoff_utc ): array {
		$cm  = $this->table();
		$sql = "SELECT * FROM `{$cm}`
			WHERE status = 'active' AND renews_at IS NOT NULL AND renews_at <= %s AND deleted_at IS NULL";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $cutoff_utc ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}
}
