<?php
/**
 * Customer portal REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Portal\PortalManager;
use Bookora\Portal\PortalToken;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Public, token-scoped endpoints for the customer self-service portal.
 *
 * Every endpoint except request-link requires a valid portal token in the
 * `X-Bookora-Portal-Token` header; the resolved customer id scopes all data.
 * Defended by the global per-IP rate limiter.
 */
final class PortalController extends AbstractController {

	private PortalManager $manager;

	/**
	 * Constructor.
	 *
	 * @param PortalManager $manager Portal manager.
	 */
	public function __construct( PortalManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$open = '__return_true';

		register_rest_route(
			self::REST_NAMESPACE,
			'/portal/request-link',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'request_link' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/portal/me',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'me' ),
					'permission_callback' => $open,
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'update_me' ),
					'permission_callback' => $open,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/portal/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'bookings' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/portal/bookings/(?P<id>\d+)/reschedule',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reschedule' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/portal/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/portal/bookings/(?P<id>\d+)/invoice',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'invoice' ),
				'permission_callback' => $open,
			)
		);
	}

	/**
	 * Request a magic link. Always returns success (no email enumeration).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function request_link( WP_REST_Request $request ): WP_REST_Response {
		$body = (array) $request->get_json_params();
		$this->manager->request_link( (string) ( $body['email'] ?? '' ) );

		return $this->success( array( 'sent' => true ) );
	}

	/**
	 * Current customer profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function me( WP_REST_Request $request ) {
		$customer_id = $this->resolve( $request );
		if ( $customer_id instanceof \WP_Error ) {
			return $customer_id;
		}
		$profile = $this->manager->profile( $customer_id );
		if ( null === $profile ) {
			return $this->error( 'bookora_not_found', __( 'Customer not found.', 'bookora' ), 404 );
		}

		return $this->success( $profile );
	}

	/**
	 * Update the current customer profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_me( WP_REST_Request $request ) {
		$customer_id = $this->resolve( $request );
		if ( $customer_id instanceof \WP_Error ) {
			return $customer_id;
		}
		$result = $this->manager->update_profile( $customer_id, (array) $request->get_json_params() );

		return is_wp_error( $result ) ? $result : $this->success( $result );
	}

	/**
	 * Current customer bookings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function bookings( WP_REST_Request $request ) {
		$customer_id = $this->resolve( $request );
		if ( $customer_id instanceof \WP_Error ) {
			return $customer_id;
		}

		return $this->success( $this->manager->bookings( $customer_id ) );
	}

	/**
	 * Reschedule a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function reschedule( WP_REST_Request $request ) {
		$customer_id = $this->resolve( $request );
		if ( $customer_id instanceof \WP_Error ) {
			return $customer_id;
		}
		$body   = (array) $request->get_json_params();
		$result = $this->manager->reschedule( $customer_id, (int) $request['id'], (string) ( $body['start'] ?? '' ) );

		return is_wp_error( $result ) ? $result : $this->success( $result );
	}

	/**
	 * Cancel a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function cancel( WP_REST_Request $request ) {
		$customer_id = $this->resolve( $request );
		if ( $customer_id instanceof \WP_Error ) {
			return $customer_id;
		}
		$result = $this->manager->cancel( $customer_id, (int) $request['id'] );

		return is_wp_error( $result ) ? $result : $this->success( $result );
	}

	/**
	 * Invoice for a booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function invoice( WP_REST_Request $request ) {
		$customer_id = $this->resolve( $request );
		if ( $customer_id instanceof \WP_Error ) {
			return $customer_id;
		}
		$result = $this->manager->invoice( $customer_id, (int) $request['id'] );

		return is_wp_error( $result ) ? $result : $this->success( $result );
	}

	/**
	 * Resolve the customer id from the portal token header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int|\WP_Error
	 */
	private function resolve( WP_REST_Request $request ): int|\WP_Error {
		$token       = (string) $request->get_header( 'X-Bookora-Portal-Token' );
		$customer_id = '' !== $token ? PortalToken::verify( $token ) : null;
		if ( null === $customer_id ) {
			return $this->error( 'bookora_portal_unauthorized', __( 'Please sign in again.', 'bookora' ), 401 );
		}

		return $customer_id;
	}
}
