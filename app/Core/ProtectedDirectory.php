<?php
/**
 * Web-inaccessible private storage directories.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and hardens private directories under uploads for sensitive plugin
 * data (backups, logs).
 *
 * Defence in depth across web servers:
 *  - **Apache**: an `.htaccess` denying all access.
 *  - **IIS**: a `web.config` denying all users.
 *  - **all servers**: an `index.php` to defeat directory listing, and an
 *    **unguessable, per-site directory name** (HMAC of a site salt) so paths
 *    cannot be enumerated even where per-directory config is ignored (nginx).
 *
 * Sensitive payloads written into these directories should additionally be
 * encrypted at rest, so an exposed file is useless on its own.
 */
final class ProtectedDirectory {

	/**
	 * Absolute path to a per-site private directory for the given label.
	 *
	 * @param string $label Short directory label (e.g. "backups", "logs").
	 * @return string
	 */
	public static function path( string $label ): string {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );

		return $base . 'bookora-' . sanitize_key( $label ) . '-' . self::token();
	}

	/**
	 * Create the directory (if needed) and (re)write its guard files.
	 *
	 * @param string $dir Absolute directory path.
	 * @return bool Whether the directory exists and is usable.
	 */
	public static function ensure( string $dir ): bool {
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		self::harden( $dir );

		return true;
	}

	/**
	 * Write the per-server guard files into an existing directory.
	 *
	 * @param string $dir Absolute directory path.
	 * @return void
	 */
	public static function harden( string $dir ): void {
		$dir = rtrim( $dir, '/\\' );

		self::write_once(
			$dir . '/.htaccess',
			"# Bookora: deny all direct web access.\nRequire all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
		);
		self::write_once(
			$dir . '/web.config',
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <authorization>\n      <deny users=\"*\" />\n    </authorization>\n  </system.webServer>\n</configuration>\n"
		);
		self::write_once( $dir . '/index.php', "<?php // Silence is golden.\n" );
	}

	/**
	 * Write a guard file only if it does not already exist.
	 *
	 * @param string $path     File path.
	 * @param string $contents File contents.
	 * @return void
	 */
	private static function write_once( string $path, string $contents ): void {
		if ( ! file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, $contents );
		}
	}

	/**
	 * Stable, unguessable per-site directory suffix derived from a site salt.
	 *
	 * @return string
	 */
	private static function token(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'bookora-fallback';

		return substr( hash_hmac( 'sha256', 'bookora-protected-dir', $salt ), 0, 16 );
	}
}
