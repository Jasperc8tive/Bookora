<?php
/**
 * Outlook (Microsoft Graph) integration REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Core\Settings;
use Bookora\Integrations\Microsoft\GraphClient;
use Bookora\Integrations\Microsoft\MicrosoftTokenStore;
use Bookora\Integrations\Microsoft\OutlookSyncService;
use Bookora\Integrations\OAuthState;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the Microsoft app config, the per-staff OAuth flow, and manual sync.
 */
final class OutlookCalendarController extends AbstractController {

	private Settings $settings;
	private GraphClient $client;
	private MicrosoftTokenStore $tokens;
	private OutlookSyncService $sync;

	/**
	 * Constructor.
	 *
	 * @param Settings            $settings Settings store.
	 * @param GraphClient         $client   Graph client.
	 * @param MicrosoftTokenStore $tokens   Token store.
	 * @param OutlookSyncService  $sync     Sync service.
	 */
	public function __construct( Settings $settings, GraphClient $client, MicrosoftTokenStore $tokens, OutlookSyncService $sync ) {
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
			'/integrations/microsoft',
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
			'/integrations/microsoft/connect',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'connect' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/microsoft/(?P<staff>\d+)/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/microsoft/(?P<staff>\d+)/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'sync' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/integrations/microsoft/callback',
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
	 * Save the Microsoft app credentials (secret only overwritten when supplied).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_app( WP_REST_Request $request ): WP_REST_Response {
		$body         = (array) $request->get_json_params();
		$integrations = (array) $this->settings->get( 'integrations', array() );
		$microsoft    = (array) ( $integrations['microsoft'] ?? array() );

		if ( array_key_exists( 'client_id', $body ) ) {
			$microsoft['client_id'] = sanitize_text_field( (string) $body['client_id'] );
		}
		if ( array_key_exists( 'tenant', $body ) ) {
			$microsoft['tenant'] = sanitize_text_field( (string) $body['tenant'] );
		}
		if ( ! empty( $body['client_secret'] ) ) {
			$microsoft['client_secret'] = sanitize_text_field( (string) $body['client_secret'] );
		}
		$integrations['microsoft'] = $microsoft;
		$this->settings->set( 'integrations', $integrations );

		return $this->status();
	}

	/**
	 * Return the Microsoft consent URL for a staff member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function connect( WP_REST_Request $request ) {
		if ( ! $this->client->is_configured() ) {
			return $this->error( 'bookora_not_configured', __( 'Add your Microsoft app credentials first.', 'bookora' ), 422 );
		}
		$staff_id = (int) $request->get_param( 'staff_id' );
		if ( $staff_id <= 0 ) {
			return $this->error( 'bookora_invalid', __( 'A staff member is required.', 'bookora' ), 422 );
		}

		return $this->success( array( 'auth_url' => $this->client->auth_url( OAuthState::sign( $staff_id ), $this->redirect_uri() ) ) );
	}

	/**
	 * OAuth redirect handler: exchange code, store tokens, return to admin.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function callback( WP_REST_Request $request ) {
		$staff_id = OAuthState::verify( (string) $request->get_param( 'state' ) );
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

		wp_safe_redirect( admin_url( 'admin.php?page=bookora-integrations&outlook=connected' ) );
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
		return rest_url( self::REST_NAMESPACE . '/integrations/microsoft/callback' );
	}
}
