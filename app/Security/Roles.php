<?php
/**
 * Role & capability installer.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Creates, syncs, and removes Bookora's custom roles and grants the
 * Administrator role every Bookora capability.
 *
 * Idempotent and version-guarded: bump CAPS_VERSION when the matrix changes and
 * sync() re-applies it on the next admin load.
 */
final class Roles {

	/**
	 * Bumped whenever the capability matrix changes; triggers a re-sync.
	 */
	public const CAPS_VERSION = '1';

	/**
	 * Option storing the installed capability version.
	 */
	private const VERSION_OPTION = 'bookora_caps_version';

	/**
	 * Install custom roles and grant administrator capabilities. Idempotent.
	 *
	 * @return void
	 */
	public function install(): void {
		foreach ( PermissionMatrix::custom_roles() as $slug => $definition ) {
			// remove_role first guarantees the caps match the current matrix.
			remove_role( $slug );
			add_role(
				$slug,
				$definition['label'],
				$this->cap_map( array_merge( array( 'read' ), $definition['caps'] ) )
			);
		}

		$this->grant_administrator();
		update_option( self::VERSION_OPTION, self::CAPS_VERSION, true );
	}

	/**
	 * Remove all Bookora roles and strip Bookora caps from the Administrator.
	 *
	 * @return void
	 */
	public function remove(): void {
		foreach ( array_keys( PermissionMatrix::custom_roles() ) as $slug ) {
			remove_role( $slug );
		}

		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			foreach ( Capabilities::all() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Re-apply the matrix if the installed version is behind the code.
	 *
	 * @return void
	 */
	public function sync(): void {
		if ( get_option( self::VERSION_OPTION ) === self::CAPS_VERSION ) {
			return;
		}
		$this->install();
	}

	/**
	 * Grant every Bookora capability to the Administrator role.
	 *
	 * @return void
	 */
	private function grant_administrator(): void {
		$admin = get_role( 'administrator' );
		if ( ! $admin instanceof \WP_Role ) {
			return;
		}
		foreach ( PermissionMatrix::administrator() as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	/**
	 * Convert a flat capability list into the add_role() boolean map.
	 *
	 * @param array<int, string> $caps Capability slugs.
	 * @return array<string, bool>
	 */
	private function cap_map( array $caps ): array {
		$map = array();
		foreach ( $caps as $cap ) {
			$map[ $cap ] = true;
		}

		return $map;
	}
}
