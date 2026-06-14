<?php
/**
 * Coupon repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Coupons;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `coupons` table.
 */
final class CouponRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'coupons';
	}

	/**
	 * Find a coupon by its code (case-insensitive on stored uppercase).
	 *
	 * @param string $code Coupon code.
	 * @return array<string, mixed>|null
	 */
	public function find_by_code( string $code ): ?array {
		return $this->find_by( array( 'code' => strtoupper( trim( $code ) ) ) );
	}

	/**
	 * Atomically increment the redemption counter.
	 *
	 * @param int $id Coupon id.
	 * @return void
	 */
	public function increment_used( int $id ): void {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare( "UPDATE `{$table}` SET used = used + 1 WHERE id = %d", $id );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->query( $sql );
	}
}
