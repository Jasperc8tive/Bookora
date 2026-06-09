<?php
/**
 * Authorization guard for REST controllers.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Centralises capability checks so controllers share one authorization policy.
 */
final class Guard {

	/**
	 * Whether the current user has a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return bool
	 */
	public function can( string $capability ): bool {
		return current_user_can( $capability );
	}

	/**
	 * Build a REST permission callback requiring a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return callable(): (bool|WP_Error)
	 */
	public function require_capability( string $capability ): callable {
		return function () use ( $capability ) {
			if ( $this->can( $capability ) ) {
				return true;
			}

			return new WP_Error(
				'bookora_forbidden',
				__( 'You do not have permission to perform this action.', 'bookora' ),
				array( 'status' => rest_authorization_required_code() )
			);
		};
	}
}
