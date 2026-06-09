<?php
/**
 * Namespaced nonce helper.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over the WordPress nonce API that namespaces every action under
 * `bookora_`, keeping CSRF tokens consistent across admin forms and AJAX.
 *
 * REST writes rely on the core `wp_rest` nonce (sent as X-WP-Nonce); this helper
 * is for admin-post / AJAX flows and any custom verification.
 */
final class Nonce {

	/**
	 * Prefix applied to every nonce action.
	 */
	private const PREFIX = 'bookora_';

	/**
	 * Create a nonce for an action.
	 *
	 * @param string $action Unprefixed action name.
	 * @return string
	 */
	public function create( string $action ): string {
		return wp_create_nonce( self::PREFIX . $action );
	}

	/**
	 * Verify a nonce for an action.
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Unprefixed action name.
	 * @return bool
	 */
	public function verify( string $nonce, string $action ): bool {
		return false !== wp_verify_nonce( $nonce, self::PREFIX . $action );
	}

	/**
	 * Render a hidden nonce field for an admin form.
	 *
	 * @param string $action  Unprefixed action name.
	 * @param string $name    Field name.
	 * @param bool   $referer Whether to include the referer field.
	 * @return string
	 */
	public function field( string $action, string $name = '_bookora_nonce', bool $referer = true ): string {
		return wp_nonce_field( self::PREFIX . $action, $name, $referer, false );
	}
}
