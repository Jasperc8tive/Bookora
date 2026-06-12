<?php
/**
 * Public payment REST controller (booking wizard).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Payments\PaymentManager;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Lets the front-end wizard list gateways, start a payment, and poll status.
 *
 * Public, but state-changing only to the extent of creating a charge a visitor
 * intends to pay; payment is confirmed server-side by the signed webhook, never
 * by the client.
 */
final class PublicPaymentController extends AbstractController {

	/**
	 * Payment manager.
	 *
	 * @var PaymentManager
	 */
	private PaymentManager $manager;

	/**
	 * Constructor.
	 *
	 * @param PaymentManager $manager Payment manager.
	 */
	public function __construct( PaymentManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$open = '__return_true';

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/pay/gateways',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'gateways' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/pay/initialize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'initialize' ),
				'permission_callback' => $open,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/book/pay/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $open,
			)
		);
	}

	/**
	 * Enabled gateways for the wizard.
	 *
	 * @return WP_REST_Response
	 */
	public function gateways(): WP_REST_Response {
		return $this->success( array( 'gateways' => $this->manager->available_gateways() ) );
	}

	/**
	 * Start a hosted payment and return the redirect URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function initialize( WP_REST_Request $request ) {
		$body         = (array) $request->get_json_params();
		$callback_url = esc_url_raw( (string) ( $body['callback_url'] ?? home_url( '/' ) ) );

		$result = $this->manager->initialize(
			(int) ( $body['appointment_id'] ?? 0 ),
			(string) ( $body['gateway'] ?? '' ),
			(string) ( $body['type'] ?? 'full' ),
			$callback_url
		);

		return is_wp_error( $result ) ? $result : $this->success( $result, 201 );
	}

	/**
	 * Public-safe payment status by reference.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function status( WP_REST_Request $request ) {
		$status = $this->manager->status( (string) $request->get_param( 'reference' ) );
		if ( null === $status ) {
			return $this->error( 'bookora_not_found', __( 'Payment not found.', 'bookora' ), 404 );
		}

		return $this->success( $status );
	}
}
