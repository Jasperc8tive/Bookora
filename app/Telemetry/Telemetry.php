<?php
/**
 * Opt-in, anonymised usage telemetry.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Telemetry;

use Bookora\Core\Settings;
use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a small, anonymised usage snapshot to Bookora so the product roadmap can
 * be driven by real (aggregate) usage. **Opt-in only** — nothing leaves the site
 * unless the admin enables it. The site identifier is a one-way hash; no
 * customer, booking, or PII data is ever included.
 */
final class Telemetry {

	private const CRON_HOOK = 'bookora_telemetry_send';

	private Settings $settings;
	private Schema $schema;
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param Settings   $settings Settings.
	 * @param Schema     $schema   Schema.
	 * @param \wpdb|null $wpdb     Database handle (defaults to global).
	 */
	public function __construct( Settings $settings, Schema $schema, ?\wpdb $wpdb = null ) {
		$this->settings = $settings;
		$this->schema   = $schema;
		$this->wpdb     = $wpdb ?? $GLOBALS['wpdb'];
	}

	/**
	 * Whether telemetry is enabled.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		$telemetry = $this->settings->get( 'telemetry', array() );

		return is_array( $telemetry ) && ! empty( $telemetry['enabled'] );
	}

	/**
	 * Schedule (or unschedule) the weekly send based on the opt-in setting.
	 *
	 * @return void
	 */
	public function sync_schedule(): void {
		$scheduled = (bool) wp_next_scheduled( self::CRON_HOOK );
		if ( $this->enabled() && ! $scheduled ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
		} elseif ( ! $this->enabled() && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Cron handler: build and send the snapshot (no-op when disabled).
	 *
	 * @return void
	 */
	public function send(): void {
		if ( ! $this->enabled() ) {
			return;
		}

		$url = $this->api_url();
		if ( '' === $url ) {
			return;
		}

		wp_remote_post(
			$url,
			array(
				'timeout'  => 15,
				'blocking' => false,
				'body'     => wp_json_encode( $this->payload() ),
				'headers'  => array( 'Content-Type' => 'application/json' ),
			)
		);
	}

	/**
	 * The anonymised snapshot.
	 *
	 * @return array<string, mixed>
	 */
	public function payload(): array {
		global $wp_version;

		$payments = $this->settings->get( 'payments', array() );
		$gateways = array();
		if ( is_array( $payments ) ) {
			foreach ( $payments as $name => $cfg ) {
				if ( is_array( $cfg ) && ! empty( $cfg['enabled'] ) ) {
					$gateways[] = (string) $name;
				}
			}
		}

		$payload = array(
			'site_hash'      => $this->site_hash(),
			'plugin_version' => defined( 'BOOKORA_VERSION' ) ? BOOKORA_VERSION : '',
			'php_version'    => PHP_VERSION,
			'wp_version'     => (string) $wp_version,
			'locale'         => function_exists( 'get_locale' ) ? get_locale() : 'en_US',
			'currency'       => (string) $this->settings->get( 'currency', '' ),
			'counts'         => array(
				'services'     => $this->count( 'services' ),
				'staff'        => $this->count( 'staff' ),
				'customers'    => $this->count( 'customers' ),
				'appointments' => $this->count( 'appointments' ),
			),
			'gateways'       => $gateways,
		);

		/**
		 * Filter the telemetry payload before it is sent. Return a payload with
		 * fewer keys to share less, or add aggregate metrics.
		 *
		 * @param array<string, mixed> $payload Snapshot.
		 */
		return (array) apply_filters( 'bookora_telemetry_payload', $payload );
	}

	/**
	 * Telemetry endpoint (filterable).
	 *
	 * @return string
	 */
	private function api_url(): string {
		/**
		 * Filter the telemetry endpoint URL. Empty disables sending.
		 *
		 * @param string $url Endpoint URL.
		 */
		return (string) apply_filters( 'bookora_telemetry_api_url', '' );
	}

	/**
	 * One-way, salted site identifier (no reversible site info).
	 *
	 * @return string
	 */
	private function site_hash(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt() : 'bookora';

		return hash( 'sha256', 'bookora-site|' . home_url() . '|' . $salt );
	}

	/**
	 * Live, non-deleted row count for a table.
	 *
	 * @param string $table Unprefixed table name.
	 * @return int
	 */
	private function count( string $table ): int {
		$name = $this->schema->table( $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM `{$name}` WHERE deleted_at IS NULL" );
	}
}
