<?php
/**
 * Booking-hold repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Appointments;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Short-lived slot reservations. Rows expire; never soft-deleted.
 */
final class BookingHoldRepository extends AbstractRepository {

	/**
	 * Holds are hard-deleted on expiry/consumption.
	 *
	 * @var bool
	 */
	protected bool $soft_deletes = false;

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'booking_holds';
	}

	/**
	 * Active (unexpired) holds for a staff member overlapping a UTC window,
	 * optionally excluding one session's own holds.
	 *
	 * @param int    $staff_id      Staff id.
	 * @param string $from_utc      Window start (UTC).
	 * @param string $to_utc        Window end (UTC).
	 * @param string $now_utc       Current time (UTC).
	 * @param string $exclude_token Session token to ignore (its own hold).
	 * @return array<int, array<string, mixed>>
	 */
	public function active_in_range( int $staff_id, string $from_utc, string $to_utc, string $now_utc, string $exclude_token = '' ): array {
		$table = $this->table();
		$sql   = "SELECT * FROM `{$table}`
			WHERE staff_id = %d AND expires_at > %s AND start_at < %s AND end_at > %s AND session_token <> %s
			ORDER BY start_at ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $staff_id, $now_utc, $to_utc, $from_utc, $exclude_token ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete all holds for a session token.
	 *
	 * @param string $token Session token.
	 * @return void
	 */
	public function delete_by_token( string $token ): void {
		$this->wpdb->delete( $this->table(), array( 'session_token' => $token ), array( '%s' ) );
	}

	/**
	 * Purge expired holds.
	 *
	 * @param string $now_utc Current time (UTC).
	 * @return void
	 */
	public function purge_expired( string $now_utc ): void {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM `{$table}` WHERE expires_at <= %s", $now_utc ) );
	}
}
