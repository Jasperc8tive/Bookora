<?php
/**
 * Payment webhook REST controller.
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
 * Receives signed gateway webhooks. Public, but each request is verified by the
 * gateway's signature before any state changes.
 */
final class PaymentWebhookController extends AbstractController {

	/**
	 * Signature header per gateway.
	 *
	 * @var array<string, string>
	 */
	private const SIGNATURE_HEADERS = array(
		'paystack'    => 'x-paystack-signature',
		'flutterwave' => 'verif-hash',
		'stripe'      => 'stripe-signature',
	);

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
		register_rest_route(
			self::REST_NAMESPACE,
			'/payments/webhook/(?P<gateway>[a-z]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify and process a webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$gateway   = (string) $request['gateway'];
		$header    = self::SIGNATURE_HEADERS[ $gateway ] ?? '';
		$signature = '' !== $header ? (string) $request->get_header( $header ) : '';

		$result = $this->manager->handle_webhook( $gateway, $request->get_body(), $signature );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}
}
