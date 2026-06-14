<?php
/**
 * Self-hosted plugin updater.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Updates;

use Bookora\Licensing\LicenseManager;
use stdClass;

defined( 'ABSPATH' ) || exit;

/**
 * Delivers updates for the commercially-distributed plugin from Bookora's own
 * update server (premium builds are not on wp.org).
 *
 * Hooks the update transient and the plugin-information modal. The remote
 * response is cached for 12h and is license-aware: only sites with a valid
 * license are offered the download package.
 */
final class Updater {

	private const CACHE_KEY = 'bookora_update_check';
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	private LicenseManager $license;

	/**
	 * Constructor.
	 *
	 * @param LicenseManager $license License manager.
	 */
	public function __construct( LicenseManager $license ) {
		$this->license = $license;
	}

	/**
	 * Register update hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'flush_cache' ), 10, 0 );
	}

	/**
	 * Update-server endpoint (filterable; empty disables update checks).
	 *
	 * @return string
	 */
	private function api_url(): string {
		/**
		 * Filter the update server URL.
		 *
		 * @param string $url Endpoint URL.
		 */
		return (string) apply_filters( 'bookora_update_api_url', '' );
	}

	/**
	 * Inject an available update into the plugins update transient.
	 *
	 * @param mixed $transient Update transient (object) or null.
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! $transient instanceof stdClass ) {
			$transient = new stdClass();
		}
		if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$remote = $this->remote_info();
		if ( null === $remote ) {
			return $transient;
		}

		$current  = defined( 'BOOKORA_VERSION' ) ? BOOKORA_VERSION : '0';
		$basename = defined( 'BOOKORA_BASENAME' ) ? BOOKORA_BASENAME : 'bookora/bookora.php';

		if ( version_compare( (string) $remote['version'], $current, '>' ) ) {
			$update               = new stdClass();
			$update->slug         = 'bookora';
			$update->plugin       = $basename;
			$update->new_version  = (string) $remote['version'];
			$update->url          = (string) ( $remote['homepage'] ?? '' );
			$update->package      = $this->license->is_valid() ? (string) ( $remote['package'] ?? '' ) : '';
			$update->tested       = (string) ( $remote['tested'] ?? '' );
			$update->requires     = (string) ( $remote['requires'] ?? '' );
			$update->requires_php = (string) ( $remote['requires_php'] ?? '' );

			$transient->response[ $basename ] = $update;
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the "View details" modal.
	 *
	 * @param mixed  $result Default result.
	 * @param string $action Requested action.
	 * @param object $args   Request args.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'bookora' !== $args->slug ) {
			return $result;
		}

		$remote = $this->remote_info();
		if ( null === $remote ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = (string) ( $remote['name'] ?? 'Bookora' );
		$info->slug          = 'bookora';
		$info->version       = (string) $remote['version'];
		$info->author        = (string) ( $remote['author'] ?? 'Bookora' );
		$info->homepage      = (string) ( $remote['homepage'] ?? '' );
		$info->requires      = (string) ( $remote['requires'] ?? '' );
		$info->requires_php  = (string) ( $remote['requires_php'] ?? '' );
		$info->tested        = (string) ( $remote['tested'] ?? '' );
		$info->download_link = $this->license->is_valid() ? (string) ( $remote['package'] ?? '' ) : '';
		$info->sections      = array(
			'description' => (string) ( $remote['description'] ?? '' ),
			'changelog'   => (string) ( $remote['changelog'] ?? '' ),
		);

		return $info;
	}

	/**
	 * Clear the cached update check (after an update completes).
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Fetch (and cache) the remote version manifest.
	 *
	 * @return array<string, mixed>|null
	 */
	private function remote_info(): ?array {
		$url = $this->api_url();
		if ( '' === $url ) {
			return null;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			// An empty array is a cached "miss" (server unreachable / no update).
			return array() === $cached ? null : $cached;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'action'  => 'info',
					'key'     => $this->license->get_key(),
					'site'    => home_url(),
					'version' => defined( 'BOOKORA_VERSION' ) ? BOOKORA_VERSION : '',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Cache the miss briefly so a flapping server doesn't slow every page.
			set_transient( self::CACHE_KEY, array(), 10 * MINUTE_IN_SECONDS );
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_transient( self::CACHE_KEY, array(), 10 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

		return $data;
	}
}
