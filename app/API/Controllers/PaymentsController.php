<?php
/**
 * Admin payments REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Core\Settings;
use Bookora\Payments\PaymentManager;
use Bookora\Payments\PaymentRepository;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Payments admin surface: list, manual record, refund, invoice, receipt, and
 * gateway settings.
 */
final class PaymentsController extends AbstractController {

	private PaymentManager $manager;
	private PaymentRepository $payments;
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param PaymentManager    $manager  Payment manager.
	 * @param PaymentRepository $payments Payment repository.
	 * @param Settings          $settings Settings store.
	 */
	public function __construct( PaymentManager $manager, PaymentRepository $payments, Settings $settings ) {
		$this->manager  = $manager;
		$this->payments = $payments;
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_PAYMENTS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/payments/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $this->require_capability( Capabilities::MANAGE_SETTINGS ),
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => $this->require_capability( Capabilities::MANAGE_SETTINGS ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/payments/(?P<id>\d+)/refund',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refund' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/payments/(?P<id>\d+)/receipt',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'receipt' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/appointments/(?P<id>\d+)/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'for_appointment' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/appointments/(?P<id>\d+)/payments/manual',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'manual' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/appointments/(?P<id>\d+)/invoice',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'invoice' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * Recent payments.
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		return $this->success( array( 'items' => $this->payments->recent() ) );
	}

	/**
	 * Payments for an appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function for_appointment( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'items' => $this->payments->for_appointment( (int) $request['id'] ) ) );
	}

	/**
	 * Record a manual payment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function manual( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$result = $this->manager->record_manual(
			(int) $request['id'],
			(float) ( $body['amount'] ?? 0 ),
			(string) ( $body['method'] ?? 'cash' )
		);

		return is_wp_error( $result ) ? $result : $this->success( $result, 201 );
	}

	/**
	 * Refund a payment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function refund( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$result = $this->manager->refund( (int) $request['id'], (float) ( $body['amount'] ?? 0 ) );

		return is_wp_error( $result ) ? $result : $this->success( $result );
	}

	/**
	 * Invoice for an appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function invoice( WP_REST_Request $request ) {
		$result = $this->manager->invoice( (int) $request['id'] );

		return is_wp_error( $result ) ? $result : $this->success( $result );
	}

	/**
	 * Receipt for a payment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function receipt( WP_REST_Request $request ) {
		$result = $this->manager->receipt( (int) $request['id'] );

		return is_wp_error( $result ) ? $result : $this->success( $result );
	}

	/**
	 * Current gateway settings (secrets masked).
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		$payments = (array) $this->settings->get( 'payments', array() );
		$masked   = array();
		foreach ( $payments as $gateway => $config ) {
			$config             = (array) $config;
			$masked[ $gateway ] = array(
				'enabled'     => ! empty( $config['enabled'] ),
				'public_key'  => (string) ( $config['public_key'] ?? $config['publishable_key'] ?? '' ),
				'has_secret'  => '' !== (string) ( $config['secret_key'] ?? '' ),
				'has_webhook' => '' !== (string) ( $config['webhook_secret'] ?? $config['secret_hash'] ?? '' ),
			);
		}

		return $this->success( array( 'payments' => $masked ) );
	}

	/**
	 * Update gateway settings. Empty secret fields are left unchanged.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$params  = (array) $request->get_json_params();
		$input   = isset( $params['payments'] ) && is_array( $params['payments'] ) ? $params['payments'] : array();
		$current = (array) $this->settings->get( 'payments', array() );

		foreach ( $current as $gateway => $config ) {
			$config   = (array) $config;
			$incoming = (array) ( $input[ $gateway ] ?? array() );

			if ( array_key_exists( 'enabled', $incoming ) ) {
				$config['enabled'] = (bool) $incoming['enabled'];
			}
			foreach ( array( 'public_key', 'publishable_key' ) as $field ) {
				if ( array_key_exists( $field, $config ) && array_key_exists( $field, $incoming ) ) {
					$config[ $field ] = sanitize_text_field( (string) $incoming[ $field ] );
				}
			}
			// Secrets: only overwrite when a non-empty value is supplied.
			foreach ( array( 'secret_key', 'webhook_secret', 'secret_hash' ) as $field ) {
				if ( array_key_exists( $field, $config ) && ! empty( $incoming[ $field ] ) ) {
					$config[ $field ] = sanitize_text_field( (string) $incoming[ $field ] );
				}
			}
			$current[ $gateway ] = $config;
		}

		$this->settings->set( 'payments', $current );

		return $this->get_settings();
	}
}
