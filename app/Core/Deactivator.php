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
		// Clear all scheduled Bookora cron events (data is retained).
		$hooks = array(
			'bookora_cron_tick',
			'bookora_notify',
			'bookora_send_reminder',
			'bookora_membership_renewals',
			'bookora_license_refresh',
			'bookora_telemetry_send',
		);
		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		// Flush rewrite rules in case later stages registered endpoints.
		flush_rewrite_rules();
	}
}
