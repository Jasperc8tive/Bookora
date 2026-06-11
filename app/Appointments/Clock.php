<?php
/**
 * Timezone-aware clock for the scheduling engine.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Appointments;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Converts between the business's local wall-clock time (used for working hours
 * and the requested booking date) and UTC (used for storage and conflict math).
 *
 * All persisted appointment times are UTC; all availability rules are local.
 */
final class Clock {

	/**
	 * Business/scheduling timezone.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $zone;

	/**
	 * UTC timezone.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $utc;

	/**
	 * Constructor.
	 *
	 * @param DateTimeZone|null $zone Scheduling timezone (defaults to the WP site timezone).
	 */
	public function __construct( ?DateTimeZone $zone = null ) {
		$this->zone = $zone ?? wp_timezone();
		$this->utc  = new DateTimeZone( 'UTC' );
	}

	/**
	 * The scheduling timezone.
	 *
	 * @return DateTimeZone
	 */
	public function zone(): DateTimeZone {
		return $this->zone;
	}

	/**
	 * Weekday (0=Sunday … 6=Saturday) for a local calendar date.
	 *
	 * @param string $date Date as Y-m-d.
	 * @return int
	 */
	public function weekday( string $date ): int {
		return (int) ( new DateTimeImmutable( $date . ' 00:00:00', $this->zone ) )->format( 'w' );
	}

	/**
	 * Epoch for a local "Y-m-d H:i(:s)" wall-clock time.
	 *
	 * @param string $local Local datetime.
	 * @return int Unix timestamp.
	 */
	public function epoch( string $local ): int {
		return ( new DateTimeImmutable( $local, $this->zone ) )->getTimestamp();
	}

	/**
	 * Convert an epoch to a UTC "Y-m-d H:i:s" string for storage.
	 *
	 * @param int $epoch Unix timestamp.
	 * @return string
	 */
	public function utc_string( int $epoch ): string {
		return ( new DateTimeImmutable( '@' . $epoch ) )->setTimezone( $this->utc )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Convert an epoch to a local "Y-m-d H:i:s" string for display.
	 *
	 * @param int $epoch Unix timestamp.
	 * @return string
	 */
	public function local_string( int $epoch ): string {
		return ( new DateTimeImmutable( '@' . $epoch ) )->setTimezone( $this->zone )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Epoch for a stored UTC "Y-m-d H:i:s" string.
	 *
	 * @param string $utc UTC datetime.
	 * @return int
	 */
	public function utc_to_epoch( string $utc ): int {
		return ( new DateTimeImmutable( $utc, $this->utc ) )->getTimestamp();
	}

	/**
	 * UTC bounds [start, endExclusive] for a local calendar day, as strings.
	 *
	 * @param string $date Date as Y-m-d.
	 * @return array{0: string, 1: string}
	 */
	public function day_bounds_utc( string $date ): array {
		$start = new DateTimeImmutable( $date . ' 00:00:00', $this->zone );
		$end   = $start->modify( '+1 day' );

		return array(
			$start->setTimezone( $this->utc )->format( 'Y-m-d H:i:s' ),
			$end->setTimezone( $this->utc )->format( 'Y-m-d H:i:s' ),
		);
	}
}
