<?php
/**
 * Gift card repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\GiftCards;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `gift_cards` table.
 */
final class GiftCardRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'gift_cards';
	}

	/**
	 * Find an active gift card by code.
	 *
	 * @param string $code Card code.
	 * @return array<string, mixed>|null
	 */
	public function find_by_code( string $code ): ?array {
		return $this->find_by( array( 'code' => strtoupper( trim( $code ) ) ) );
	}

	/**
	 * Debit a card's balance atomically (only if sufficient funds).
	 *
	 * @param int   $id     Card id.
	 * @param float $amount Amount to debit.
	 * @return bool True if the debit applied.
	 */
	public function debit( int $id, float $amount ): bool {
		$table = $this->table();
		// Identifier is allowlisted (Schema::table); values are bound via prepare().
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"UPDATE `{$table}` SET balance = balance - %f WHERE id = %d AND balance >= %f",
			$amount,
			$id,
			$amount
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->query( $sql );

		return 1 === (int) $rows;
	}
}
