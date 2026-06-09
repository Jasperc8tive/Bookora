<?php
/**
 * Activation routine.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Bookora\Database\MigrationRunner;
use Bookora\Security\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation: creates the schema and seeds default settings.
 */
final class Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Build the schema up to the current code version.
		( new MigrationRunner() )->migrate();
		update_option( 'bookora_db_version', BOOKORA_DB_VERSION, false );

		// Seed default settings without clobbering any existing values.
		( new Settings() )->seed_defaults();

		// Install custom roles + capabilities (Manager / Staff / Customer + admin grants).
		( new Roles() )->install();

		// Record first-install metadata.
		if ( false === get_option( 'bookora_installed_at', false ) ) {
			update_option( 'bookora_installed_at', time(), false );
		}
		update_option( 'bookora_version', BOOKORA_VERSION, false );
	}
}
