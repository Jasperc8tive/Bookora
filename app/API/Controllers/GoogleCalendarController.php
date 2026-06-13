<?php
/**
 * Google Calendar integration REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Core\Settings;
use Bookora\Integrations\Google\CalendarSyncService;
use Bookora\Integrations\Google\GoogleClient;
use Bookora\Integrations\Google\GoogleTokenStore;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the Google app config, the per-staff OAuth flow, and manual sync.
 */
final class GoogleCalendarController extends AbstractController {

	private Settings $settings;
	private GoogleClient $client;
	private GoogleTokenStore $tokens;
	private CalendarSyncService $sync;

	/**
	 * Constructor.
	 *
	 * @param Settings            $settings Settings store.
	 * @param GoogleClient        $client   Google client.
	 * @param GoogleTokenStore    $tokens   Token store.
	 * @param CalendarSyncService $sync     Sync service.
	 */
	public function __construct( Settings $settings, GoogleClient $client, GoogleTokenStore $tokens, CalendarSyncService $sync ) {
		$this->settings = $settings;
		$this->client   = $client;
		$this->tokens   = $tokens;
		$this->sync     = $sync;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$manage = $this->require_capability( Capabilities::MANAGE_SETTINGS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/google',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'status' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'save_app' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/google/connect',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'connect' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/google/(?P<staff>\d+)/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/google/(?P<staff>\d+)/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'sync' ),
				'permission_callback' => $manage,
			)
		);

		// OAuth redirect target — public, but the signed state is verified.
		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/google/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Connection status + per-staff connection flags.
	 *
	 * @return WP_REST_Response
	 */
	public function status(): WP_REST_Response {
		return $this->success(
			array(
				'configured' => $this->client->is_configured(),
				'connected'  => $this->tokens->connected_staff_ids(),
			)
		);
	}

	/**
	 * Save the Google app credentials (secret only overwritten when supplied).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_app( WP_REST_Request $request ): WP_REST_Response {
		$body         = (array) $request->get_json_params();
		$integrations = (array) $this->settings->get( 'integrations', array() );
		$google       = (array) ( $integrations['google'] ?? array() );

		if ( array_key_exists( 'client_id', $body ) ) {
			$google['client_id'] = sanitize_text_field( (string) $body['client_id'] );
		}
		if ( ! empty( $body['client_secret'] ) ) {
			$google['client_secret'] = sanitize_text_field( (string) $body['client_secret'] );
		}
		$integrations['google'] = $google;
		$this->settings->set( 'integrations', $integrations );

		return $this->status();
	}

	/**
	 * Return the Google consent URL for a staff member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function connect( WP_REST_Request $request ) {
		if ( ! $this->client->is_configured() ) {
			return $this->error( 'bookora_not_configured', __( 'Add your Google app credentials first.', 'bookora' ), 422 );
		}
		$staff_id = (int) $request->get_param( 'staff_id' );
		if ( $staff_id <= 0 ) {
			return $this->error( 'bookora_invalid', __( 'A staff member is required.', 'bookora' ), 422 );
		}

		$url = $this->client->auth_url( $this->sign_state( $staff_id ), $this->redirect_uri() );

		return $this->success( array( 'auth_url' => $url ) );
	}

	/**
	 * OAuth redirect handler: exchange code, store tokens, return to admin.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function callback( WP_REST_Request $request ) {
		$staff_id = $this->verify_state( (string) $request->get_param( 'state' ) );
		$code     = (string) $request->get_param( 'code' );
		if ( null === $staff_id || '' === $code ) {
			return $this->error( 'bookora_invalid_state', __( 'Invalid or expired authorization.', 'bookora' ), 400 );
		}

		$tokens = $this->client->exchange_code( $code, $this->redirect_uri() );
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		$tokens['calendar_id'] = 'primary';
		$this->tokens->store( $staff_id, $tokens );

		wp_safe_redirect( admin_url( 'admin.php?page=bookora-integrations&google=connected' ) );
		exit;
	}

	/**
	 * Disconnect a staff member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$this->tokens->disconnect( (int) $request['staff'] );

		return $this->success( array( 'disconnected' => true ) );
	}

	/**
	 * Warm the busy cache for a staff member now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function sync( WP_REST_Request $request ): WP_REST_Response {
		$this->sync->warm_busy_cache( (int) $request['staff'] );

		return $this->success( array( 'synced' => true ) );
	}

	/**
	 * The OAuth redirect URI (this controller's callback).
	 *
	 * @return string
	 */
	private function redirect_uri(): string {
		return rest_url( self::REST_NAMESPACE . '/integrations/google/callback' );
	}

	/**
	 * Sign an OAuth state binding the staff id + a timestamp.
	 *
	 * @param int $staff_id Staff id.
	 * @return string
	 */
	private function sign_state( int $staff_id ): string {
		$payload = $staff_id . '|' . time();
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );

		return rtrim( strtr( base64_encode( $payload . '|' . $sig ), '+/', '-_' ), '=' );
	}

	/**
	 * Verify an OAuth state and return the staff id (or null).
	 *
	 * @param string $state State value.
	 * @return int|null
	 */
	private function verify_state( string $state ): ?int {
		$decoded = base64_decode( strtr( $state, '-_', '+/' ), true );
		if ( false === $decoded ) {
			return null;
		}
		$parts = explode( '|', $decoded );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		list( $staff_id, $ts, $sig ) = $parts;

		$expected = hash_hmac( 'sha256', $staff_id . '|' . $ts, wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}
		if ( time() - (int) $ts > 900 ) {
			return null;
		}

		return (int) $staff_id;
	}
}
