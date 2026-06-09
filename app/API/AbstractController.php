<?php
/**
 * Base REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Shared behaviour for Bookora REST controllers: namespace, permission helpers,
 * and a consistent success/error envelope.
 */
abstract class AbstractController {

	/**
	 * REST namespace for all Bookora routes.
	 */
	public const REST_NAMESPACE = 'bookora/v1';

	/**
	 * Register this controller's routes. Called on rest_api_init.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Build a permission callback that requires a capability.
	 *
	 * Cookie-authenticated SPA requests are additionally nonce-checked by WP core
	 * (rest_cookie_check_errors) when the X-WP-Nonce header is present.
	 *
	 * @param string $capability Required capability.
	 * @return callable(WP_REST_Request): (bool|WP_Error)
	 */
	protected function require_capability( string $capability ): callable {
		return static function () use ( $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}

			return new WP_Error(
				'bookora_forbidden',
				__( 'You do not have permission to perform this action.', 'bookora' ),
				array( 'status' => rest_authorization_required_code() )
			);
		};
	}

	/**
	 * Standard success envelope.
	 *
	 * @param mixed $data   Payload.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Standard error envelope.
	 *
	 * @param string               $code    Machine-readable code.
	 * @param string               $message Human-readable message.
	 * @param int                  $status  HTTP status.
	 * @param array<string, mixed> $details Optional extra detail.
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400, array $details = array() ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array_merge( array( 'status' => $status ), $details )
		);
	}
}
