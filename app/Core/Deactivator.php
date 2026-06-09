<?php
/**
 * Deactivation routine.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation. Never destroys data — only clears transient state.
 */
final class Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear any scheduled Bookora cron events (none registered yet in Stage 1).
		wp_clear_scheduled_hook( 'bookora_cron_tick' );

		// Flush rewrite rules in case later stages registered endpoints.
		flush_rewrite_rules();
	}
}
