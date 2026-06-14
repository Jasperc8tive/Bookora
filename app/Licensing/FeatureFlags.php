<?php
/**
 * Tier-driven feature flags.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Licensing;

use Bookora\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves which features are enabled, combining:
 *  1. the licensed tier (free|pro|agency) — the baseline,
 *  2. per-site overrides stored in settings (`feature_overrides`),
 *  3. the `bookora_feature_enabled` filter (final say, e.g. for kill-switches).
 *
 * Features fall back to "enabled" when unknown, so the gate never accidentally
 * hides core functionality.
 */
final class FeatureFlags {

	/**
	 * The minimum tier each gated feature requires. Anything not listed is a
	 * core feature available on every tier.
	 *
	 * @var array<string, string>
	 */
	private const REQUIRES = array(
		'payments'          => 'free',
		'notifications_sms' => 'pro',
		'calendar_sync'     => 'pro',
		'advanced_features' => 'pro',
		'ai_scheduling'     => 'pro',
		'reports_export'    => 'pro',
		'white_label'       => 'agency',
		'multi_location'    => 'agency',
	);

	/**
	 * Tier ranking for comparisons.
	 *
	 * @var array<string, int>
	 */
	private const RANK = array(
		'free'   => 0,
		'pro'    => 1,
		'agency' => 2,
	);

	private LicenseManager $license;
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param LicenseManager $license  License manager.
	 * @param Settings       $settings Settings.
	 */
	public function __construct( LicenseManager $license, Settings $settings ) {
		$this->license  = $license;
		$this->settings = $settings;
	}

	/**
	 * Whether a feature is enabled for this site.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public function enabled( string $feature ): bool {
		$result = $this->resolve( $feature );

		/**
		 * Filter the final enabled state of a feature.
		 *
		 * @param bool   $result  Resolved state.
		 * @param string $feature Feature key.
		 * @param string $tier    Current licensed tier.
		 */
		return (bool) apply_filters( 'bookora_feature_enabled', $result, $feature, $this->license->tier() );
	}

	/**
	 * Resolve tier + override state (pre-filter).
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	private function resolve( string $feature ): bool {
		$overrides = $this->settings->get( 'feature_overrides', array() );
		if ( is_array( $overrides ) && array_key_exists( $feature, $overrides ) ) {
			return (bool) $overrides[ $feature ];
		}

		$required = self::REQUIRES[ $feature ] ?? 'free';

		return $this->rank( $this->license->tier() ) >= $this->rank( $required );
	}

	/**
	 * Numeric rank for a tier (unknown tiers rank lowest).
	 *
	 * @param string $tier Tier key.
	 * @return int
	 */
	private function rank( string $tier ): int {
		return self::RANK[ $tier ] ?? 0;
	}

	/**
	 * Full map of feature => enabled, for the admin UI.
	 *
	 * @return array<string, bool>
	 */
	public function all(): array {
		$map = array();
		foreach ( array_keys( self::REQUIRES ) as $feature ) {
			$map[ $feature ] = $this->enabled( $feature );
		}

		return $map;
	}

	/**
	 * The minimum tier a feature requires.
	 *
	 * @param string $feature Feature key.
	 * @return string
	 */
	public function required_tier( string $feature ): string {
		return self::REQUIRES[ $feature ] ?? 'free';
	}
}
