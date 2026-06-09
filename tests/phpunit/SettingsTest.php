<?php
/**
 * Settings tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests;

use Bookora\Core\Settings;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Core\Settings
 */
class SettingsTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'bookora_settings' );
	}

	public function test_returns_defaults_when_unset(): void {
		$settings = new Settings();
		$this->assertSame( 'NGN', $settings->get( 'currency' ) );
		$this->assertFalse( $settings->get( 'delete_data_on_uninstall' ) );
	}

	public function test_set_and_get_round_trip(): void {
		$settings = new Settings();
		$settings->set( 'business_name', 'Ada Medspa' );

		$fresh = new Settings();
		$this->assertSame( 'Ada Medspa', $fresh->get( 'business_name' ) );
	}

	public function test_currency_is_sanitised_to_three_upper_letters(): void {
		$settings = new Settings();
		$settings->set( 'currency', 'usd$' );
		$this->assertSame( 'USD', $settings->get( 'currency' ) );
	}

	public function test_week_starts_on_is_clamped(): void {
		$settings = new Settings();
		$settings->set( 'week_starts_on', 99 );
		$this->assertSame( 6, $settings->get( 'week_starts_on' ) );
	}

	public function test_invalid_log_level_falls_back_to_error(): void {
		$settings = new Settings();
		$settings->set( 'log_level', 'nonsense' );
		$this->assertSame( 'error', $settings->get( 'log_level' ) );
	}

	public function test_seed_defaults_is_idempotent(): void {
		$settings = new Settings();
		$settings->seed_defaults();
		$settings->set( 'business_name', 'Kept' );
		$settings->seed_defaults();

		$this->assertSame( 'Kept', ( new Settings() )->get( 'business_name' ) );
	}
}
