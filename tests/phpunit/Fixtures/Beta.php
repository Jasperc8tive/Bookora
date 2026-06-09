<?php
/**
 * Test fixture.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Fixtures;

/**
 * A class with a single autowireable dependency.
 */
class Beta {

	/**
	 * Injected dependency.
	 *
	 * @var Alpha
	 */
	public Alpha $alpha;

	/**
	 * Constructor.
	 *
	 * @param Alpha $alpha Dependency.
	 */
	public function __construct( Alpha $alpha ) {
		$this->alpha = $alpha;
	}
}
