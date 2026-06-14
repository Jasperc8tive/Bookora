<?php
/**
 * Uninstall routine.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Bookora\Database\Schema;
use Bookora\Security\Roles;

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
		'bookora_caps_version',
		'bookora_audit_secret',
		'bookora_license',
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

		// Suspend FK checks so tables drop regardless of reference order; an
		// otherwise-correct DROP would fail on an FK-referenced parent and leave
		// data behind.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );

		foreach ( self::TABLES as $table ) {
			$name = $schema->table( $table );
			// Table identifiers cannot be parameterised; the name is built from a
			// hardcoded allowlist above, so this is safe.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
		delete_transient( 'bookora_update_check' );

		// Remove the protected on-disk directories (encrypted backups + logs).
		self::remove_directory( ProtectedDirectory::path( 'backups' ) );
		self::remove_directory( ProtectedDirectory::path( 'logs' ) );

		// Remove custom roles and revoke administrator capabilities.
		( new Roles() )->remove();
	}

	/**
	 * Recursively delete a plugin-owned directory and its contents.
	 *
	 * @param string $dir Absolute directory path.
	 * @return void
	 */
	private static function remove_directory( string $dir ): void {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$entries = glob( $dir . '/*' );
		foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
			if ( is_dir( $entry ) ) {
				self::remove_directory( $entry );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.WP.AlternativeFunctions.file_system_operations_unlink
				unlink( $entry );
			}
		}
		// Hidden guard files (.htaccess) are not matched by glob's default pattern.
		foreach ( array( '.htaccess', 'web.config' ) as $hidden ) {
			$path = $dir . '/' . $hidden;
			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.WP.AlternativeFunctions.file_system_operations_unlink
				unlink( $path );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dir );
	}
}
