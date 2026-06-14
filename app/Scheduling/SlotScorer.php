<?php
/**
 * Slot scoring contract.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Scheduling;

defined( 'ABSPATH' ) || exit;

/**
 * Scores a candidate booking slot. Swappable so a smarter (e.g. Claude-API or
 * ML) scorer can replace the built-in heuristics.
 */
interface SlotScorer {

	/**
	 * Score a slot; higher is a better suggestion.
	 *
	 * @param array<string, mixed> $slot    Slot (start_local, end_utc, staff_id).
	 * @param array<string, mixed> $context Scoring context (prefer, adjacent, days_from_now).
	 * @return float
	 */
	public function score( array $slot, array $context ): float;
}
