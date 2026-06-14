<?php
/**
 * Commercial hardening REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Branding\WhiteLabel;
use Bookora\Core\Settings;
use Bookora\DataTransfer\BackupManager;
use Bookora\DataTransfer\DataPortability;
use Bookora\Licensing\FeatureFlags;
use Bookora\Licensing\LicenseManager;
use Bookora\Security\Capabilities;
use Bookora\Telemetry\Telemetry;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints for licensing, feature flags, branding, telemetry, and
 * data import/export + backups. Everything is gated on `bookora_manage_settings`.
 */
final class CommercialController extends AbstractController {

	private LicenseManager $license;
	private FeatureFlags $features;
	private Telemetry $telemetry;
	private WhiteLabel $branding;
	private DataPortability $data;
	private BackupManager $backups;
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param LicenseManager  $license   License manager.
	 * @param FeatureFlags    $features  Feature flags.
	 * @param Telemetry       $telemetry Telemetry.
	 * @param WhiteLabel      $branding  White-label service.
	 * @param DataPortability $data      Import/export.
	 * @param BackupManager   $backups   Backups.
	 * @param Settings        $settings  Settings.
	 */
	public function __construct(
		LicenseManager $license,
		FeatureFlags $features,
		Telemetry $telemetry,
		WhiteLabel $branding,
		DataPortability $data,
		BackupManager $backups,
		Settings $settings
	) {
		$this->license   = $license;
		$this->features  = $features;
		$this->telemetry = $telemetry;
		$this->branding  = $branding;
		$this->data      = $data;
		$this->backups   = $backups;
		$this->settings  = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can   = $this->require_capability( Capabilities::MANAGE_SETTINGS );
		$route = static function ( string $path, string $methods, callable $cb ) use ( $can ): void {
			register_rest_route(
				self::REST_NAMESPACE,
				$path,
				array(
					'methods'             => $methods,
					'callback'            => $cb,
					'permission_callback' => $can,
				)
			);
		};

		$route( '/commercial/license', 'GET', array( $this, 'license_status' ) );
		$route( '/commercial/license/activate', 'POST', array( $this, 'license_activate' ) );
		$route( '/commercial/license/deactivate', 'POST', array( $this, 'license_deactivate' ) );
		$route( '/commercial/features', 'GET', array( $this, 'features_list' ) );
		$route( '/commercial/branding', 'GET', array( $this, 'branding_get' ) );
		$route( '/commercial/branding', 'POST', array( $this, 'branding_save' ) );
		$route( '/commercial/telemetry', 'GET', array( $this, 'telemetry_get' ) );
		$route( '/commercial/telemetry', 'POST', array( $this, 'telemetry_save' ) );
		$route( '/commercial/export', 'GET', array( $this, 'export' ) );
		$route( '/commercial/import', 'POST', array( $this, 'import' ) );
		$route( '/commercial/backups', 'GET', array( $this, 'backups_list' ) );
		$route( '/commercial/backups', 'POST', array( $this, 'backups_create' ) );
		$route( '/commercial/backups/restore', 'POST', array( $this, 'backups_restore' ) );
		$route( '/commercial/backups/delete', 'POST', array( $this, 'backups_delete' ) );
	}

	/**
	 * GET license status.
	 *
	 * @return WP_REST_Response
	 */
	public function license_status(): WP_REST_Response {
		return $this->success( $this->license->status() );
	}

	/**
	 * POST activate license.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function license_activate( WP_REST_Request $request ) {
		$result = $this->license->activate( (string) $request->get_param( 'key' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result );
	}

	/**
	 * POST deactivate license.
	 *
	 * @return WP_REST_Response
	 */
	public function license_deactivate(): WP_REST_Response {
		return $this->success( $this->license->deactivate() );
	}

	/**
	 * GET feature flags.
	 *
	 * @return WP_REST_Response
	 */
	public function features_list(): WP_REST_Response {
		return $this->success(
			array(
				'tier'     => $this->license->tier(),
				'features' => $this->features->all(),
			)
		);
	}

	/**
	 * GET branding settings.
	 *
	 * @return WP_REST_Response
	 */
	public function branding_get(): WP_REST_Response {
		$branding = $this->settings->get( 'branding', array() );

		return $this->success(
			array(
				'active'   => $this->branding->active(),
				'editable' => $this->features->enabled( 'white_label' ),
				'branding' => is_array( $branding ) ? $branding : array(),
			)
		);
	}

	/**
	 * POST branding settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function branding_save( WP_REST_Request $request ) {
		if ( ! $this->features->enabled( 'white_label' ) ) {
			return $this->error( 'bookora_feature_locked', __( 'White-labelling requires an agency license.', 'bookora' ), 403 );
		}

		$incoming = $request->get_param( 'branding' );
		$branding = array(
			'plugin_name'   => sanitize_text_field( (string) ( $incoming['plugin_name'] ?? '' ) ),
			'vendor_name'   => sanitize_text_field( (string) ( $incoming['vendor_name'] ?? '' ) ),
			'support_url'   => esc_url_raw( (string) ( $incoming['support_url'] ?? '' ) ),
			'logo_url'      => esc_url_raw( (string) ( $incoming['logo_url'] ?? '' ) ),
			'primary_color' => sanitize_hex_color( (string) ( $incoming['primary_color'] ?? '' ) ) ?? '',
		);
		$this->settings->set( 'branding', $branding );

		return $this->success( array( 'branding' => $branding ) );
	}

	/**
	 * GET telemetry state + payload preview.
	 *
	 * @return WP_REST_Response
	 */
	public function telemetry_get(): WP_REST_Response {
		return $this->success(
			array(
				'enabled' => $this->telemetry->enabled(),
				'preview' => $this->telemetry->payload(),
			)
		);
	}

	/**
	 * POST telemetry opt-in toggle.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function telemetry_save( WP_REST_Request $request ): WP_REST_Response {
		$enabled = (bool) $request->get_param( 'enabled' );
		$this->settings->set( 'telemetry', array( 'enabled' => $enabled ) );
		$this->telemetry->sync_schedule();

		return $this->success( array( 'enabled' => $enabled ) );
	}

	/**
	 * GET a full export document.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export( WP_REST_Request $request ): WP_REST_Response {
		$include_logs = (bool) $request->get_param( 'include_logs' );

		return $this->success( $this->data->export( $include_logs ) );
	}

	/**
	 * POST import an export document (destructive, requires confirm).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		if ( ! $request->get_param( 'confirm' ) ) {
			return $this->error( 'bookora_import_unconfirmed', __( 'Import replaces existing data and must be confirmed.', 'bookora' ), 422 );
		}
		$payload = $request->get_param( 'payload' );
		if ( ! is_array( $payload ) ) {
			return $this->error( 'bookora_import_payload', __( 'A valid export document is required.', 'bookora' ), 422 );
		}

		$result = $this->data->import( $payload, (bool) $request->get_param( 'with_settings' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( array( 'restored' => $result ) );
	}

	/**
	 * GET list backups.
	 *
	 * @return WP_REST_Response
	 */
	public function backups_list(): WP_REST_Response {
		return $this->success( array( 'items' => $this->backups->list() ) );
	}

	/**
	 * POST create a backup.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public function backups_create() {
		$result = $this->backups->create();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result, 201 );
	}

	/**
	 * POST restore a backup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function backups_restore( WP_REST_Request $request ) {
		if ( ! $request->get_param( 'confirm' ) ) {
			return $this->error( 'bookora_restore_unconfirmed', __( 'Restore replaces existing data and must be confirmed.', 'bookora' ), 422 );
		}
		$result = $this->backups->restore( (string) $request->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( array( 'restored' => $result ) );
	}

	/**
	 * POST delete a backup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function backups_delete( WP_REST_Request $request ) {
		if ( ! $this->backups->delete( (string) $request->get_param( 'id' ) ) ) {
			return $this->error( 'bookora_backup_missing', __( 'That backup could not be found.', 'bookora' ), 404 );
		}

		return $this->success( array( 'deleted' => true ) );
	}
}
