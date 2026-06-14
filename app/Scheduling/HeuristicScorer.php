<?php
/**
 * Heuristic slot scorer.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Scheduling;

defined( 'ABSPATH' ) || exit;

/**
 * Default, dependency-free slot scorer. Favours:
 *  - the customer's preferred time-of-day band,
 *  - slots adjacent to existing appointments (tighter packing, less idle time),
 *  - sooner dates (most customers want the earliest workable time).
 */
final class HeuristicScorer implements SlotScorer {

	private const BASE             = 50.0;
	private const PREFERENCE_BONUS = 20.0;
	private const ADJACENCY_BONUS  = 15.0;
	private const SOONEST_MAX      = 10.0;

	/**
	 * {@inheritDoc}
	 */
	public function score( array $slot, array $context ): float {
		$score = self::BASE;

		$prefer = (string) ( $context['prefer'] ?? '' );
		if ( '' !== $prefer && $this->band( (string) $slot['start_local'] ) === $prefer ) {
			$score += self::PREFERENCE_BONUS;
		}

		if ( ! empty( $context['adjacent'] ) ) {
			$score += self::ADJACENCY_BONUS;
		}

		$days   = max( 0, (int) ( $context['days_from_now'] ?? 0 ) );
		$score += max( 0.0, self::SOONEST_MAX - $days );

		return round( $score, 1 );
	}

	/**
	 * Map a local datetime to a time-of-day band.
	 *
	 * @param string $local Local datetime (Y-m-d H:i:s).
	 * @return string morning|afternoon|evening
	 */
	private function band( string $local ): string {
		$hour = (int) substr( $local, 11, 2 );
		if ( $hour >= 5 && $hour <= 11 ) {
			return 'morning';
		}
		if ( $hour >= 12 && $hour <= 16 ) {
			return 'afternoon';
		}

		return 'evening';
	}
}
