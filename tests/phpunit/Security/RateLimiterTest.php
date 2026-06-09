<?php
/**
 * Rate limiter tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Security;

use Bookora\Security\RateLimiter;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Security\RateLimiter
 */
class RateLimiterTest extends WP_UnitTestCase {

	private RateLimiter $limiter;
	private string $key;

	public function set_up(): void {
		parent::set_up();
		$this->limiter = new RateLimiter();
		$this->key     = 'test:' . wp_generate_uuid4();
	}

	public function tear_down(): void {
		$this->limiter->clear( $this->key );
		parent::tear_down();
	}

	public function test_allows_hits_up_to_the_limit(): void {
		$first  = $this->limiter->hit( $this->key, 3, 60 );
		$second = $this->limiter->hit( $this->key, 3, 60 );
		$third  = $this->limiter->hit( $this->key, 3, 60 );

		$this->assertTrue( $first['allowed'] );
		$this->assertTrue( $second['allowed'] );
		$this->assertTrue( $third['allowed'] );
		$this->assertSame( 0, $third['remaining'] );
	}

	public function test_blocks_after_limit_and_reports_retry_after(): void {
		$this->limiter->hit( $this->key, 2, 60 );
		$this->limiter->hit( $this->key, 2, 60 );
		$blocked = $this->limiter->hit( $this->key, 2, 60 );

		$this->assertFalse( $blocked['allowed'] );
		$this->assertGreaterThan( 0, $blocked['retry_after'] );
		$this->assertTrue( $this->limiter->too_many( $this->key, 2 ) );
	}

	public function test_clear_resets_the_window(): void {
		$this->limiter->hit( $this->key, 1, 60 );
		$this->limiter->hit( $this->key, 1, 60 );
		$this->assertTrue( $this->limiter->too_many( $this->key, 1 ) );

		$this->limiter->clear( $this->key );
		$this->assertFalse( $this->limiter->too_many( $this->key, 1 ) );
		$this->assertTrue( $this->limiter->hit( $this->key, 1, 60 )['allowed'] );
	}
}
