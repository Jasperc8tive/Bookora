<?php
/**
 * ConflictDetector overlap math (pure, no DB).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Appointments;

use Bookora\Appointments\ConflictDetector;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Appointments\ConflictDetector
 */
class ConflictDetectorTest extends WP_UnitTestCase {

	public function test_overlapping_intervals(): void {
		$this->assertTrue( ConflictDetector::overlaps( 0, 10, 5, 15 ) );
		$this->assertTrue( ConflictDetector::overlaps( 5, 15, 0, 10 ) );
		$this->assertTrue( ConflictDetector::overlaps( 0, 10, 2, 8 ) );
	}

	public function test_touching_intervals_do_not_overlap(): void {
		// Half-open: [0,10) and [10,20) are back-to-back, not overlapping.
		$this->assertFalse( ConflictDetector::overlaps( 0, 10, 10, 20 ) );
		$this->assertFalse( ConflictDetector::overlaps( 10, 20, 0, 10 ) );
	}

	public function test_disjoint_intervals(): void {
		$this->assertFalse( ConflictDetector::overlaps( 0, 10, 20, 30 ) );
	}
}
