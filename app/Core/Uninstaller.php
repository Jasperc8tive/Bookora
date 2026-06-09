<?php
/**
 * Uninstall routine.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Removes Bookora data — only when the operator has explicitly opted in.
 */
final class Uninstaller {

	/**
	 * Tables owned by Bookora (unprefixed; resolved through Schema).
	 *
	 * @var array<int, string>
	 */
	private const TABLES = array(
		'migrations',
		'services',
		'staff',
		'staff_availability',
		'customers',
		'appointments',
		'payments',
		'notes',
		'notifications',
		'waitlist',
		'resources',
		'integrations',
		'activity_logs',
	);

	/**
	 * Plugin options to remove.
	 *
	 * @var array<int, string>
	 */
	private const OPTIONS = array(
		'bookora_settings',
		'bookora_db_version',
		'bookora_version',
		'bookora_installed_at',
	);

	/**
	 * Run uninstall cleanup. No-op unless the delete-data setting is enabled.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		$settings = (array) get_option( 'bookora_settings', array() );
		$delete   = ! empty( $settings['delete_data_on_uninstall'] );

		if ( ! $delete ) {
			return;
		}

		global $wpdb;
		$schema = new Schema();

		foreach ( self::TABLES as $table ) {
			$name = $schema->table( $table );
			// Table identifiers cannot be parameterised; the name is built from a
			// hardcoded allowlist above, so this is safe.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		}

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
	}
}
