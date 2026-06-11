<?php
/**
 * Marks enqueued scripts as ES modules.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * The Vite build code-splits a shared vendor chunk (React) out of the admin and
 * front-end entries, so those entries contain ES `import` statements. Browsers
 * only honour them when the script tag is `type="module"`; this helper rewrites
 * the tag for the given handles. The shared chunk is fetched automatically by
 * the module loader and needs no separate enqueue.
 */
final class ModuleScript {

	/**
	 * Handles to emit as modules.
	 *
	 * @var array<int, string>
	 */
	private static array $handles = array();

	/**
	 * Whether the filter has been registered.
	 *
	 * @var bool
	 */
	private static bool $hooked = false;

	/**
	 * Mark one or more script handles to be output as ES modules.
	 *
	 * @param string ...$handles Script handles.
	 * @return void
	 */
	public static function enable( string ...$handles ): void {
		foreach ( $handles as $handle ) {
			self::$handles[] = $handle;
		}

		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;

		add_filter( 'script_loader_tag', array( self::class, 'filter' ), 10, 3 );
	}

	/**
	 * Rewrite the script tag to a module tag for registered handles.
	 *
	 * @param string $tag    Original tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string
	 */
	public static function filter( string $tag, string $handle, string $src ): string {
		if ( ! in_array( $handle, self::$handles, true ) ) {
			return $tag;
		}

		// This runs inside the `script_loader_tag` filter — rewriting the already
		// enqueued tag is the supported way to set type="module".
		$format = '<script type="module" src="%s" id="%s-js"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

		return sprintf( $format, esc_url( $src ), esc_attr( $handle ) );
	}
}
