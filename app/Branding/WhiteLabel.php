<?php
/**
 * White-label / rebranding (agency tier).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Branding;

use Bookora\Core\Settings;
use Bookora\Licensing\FeatureFlags;

defined( 'ABSPATH' ) || exit;

/**
 * Rebrands the plugin for agencies: the admin menu label, the Plugins-list row,
 * and the branding surfaced to the admin SPA (name, vendor, support URL, colour,
 * logo). Active only when the `white_label` feature is enabled (agency tier) and
 * a custom name is configured; otherwise everything reads "Bookora".
 */
final class WhiteLabel {

	private Settings $settings;
	private FeatureFlags $features;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings Settings.
	 * @param FeatureFlags $features Feature flags.
	 */
	public function __construct( Settings $settings, FeatureFlags $features ) {
		$this->settings = $settings;
		$this->features = $features;
	}

	/**
	 * Whether white-labelling is active.
	 *
	 * @return bool
	 */
	public function active(): bool {
		return $this->features->enabled( 'white_label' ) && '' !== $this->name( false );
	}

	/**
	 * Register branding hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Surface branding to the admin SPA regardless (the React header reads it).
		add_filter( 'bookora_admin_data', array( $this, 'inject_branding' ) );

		if ( ! $this->active() ) {
			return;
		}

		add_filter( 'all_plugins', array( $this, 'rename_plugin_row' ) );
		add_action( 'admin_menu', array( $this, 'rename_menu' ), 999 );
	}

	/**
	 * The display name ('Bookora' by default).
	 *
	 * @param bool $fallback Whether to fall back to 'Bookora' when unset.
	 * @return string
	 */
	public function name( bool $fallback = true ): string {
		$branding = $this->branding();
		$name     = isset( $branding['plugin_name'] ) ? trim( (string) $branding['plugin_name'] ) : '';
		if ( '' !== $name ) {
			return $name;
		}

		return $fallback ? 'Bookora' : '';
	}

	/**
	 * Add the branding block to the admin localisation payload.
	 *
	 * @param array<string, mixed> $data Localised data.
	 * @return array<string, mixed>
	 */
	public function inject_branding( array $data ): array {
		$branding         = $this->branding();
		$data['branding'] = array(
			'name'          => $this->name(),
			'vendor'        => (string) ( $branding['vendor_name'] ?? '' ),
			'support_url'   => (string) ( $branding['support_url'] ?? '' ),
			'logo_url'      => (string) ( $branding['logo_url'] ?? '' ),
			'primary_color' => (string) ( $branding['primary_color'] ?? '' ),
			'white_label'   => $this->active(),
		);

		return $data;
	}

	/**
	 * Rename the row on the Plugins screen.
	 *
	 * @param array<string, array<string, mixed>> $plugins Installed plugins.
	 * @return array<string, array<string, mixed>>
	 */
	public function rename_plugin_row( array $plugins ): array {
		$basename = defined( 'BOOKORA_BASENAME' ) ? BOOKORA_BASENAME : '';
		if ( '' !== $basename && isset( $plugins[ $basename ] ) ) {
			$branding                       = $this->branding();
			$plugins[ $basename ]['Name']   = $this->name();
			$plugins[ $basename ]['Title']  = $this->name();
			$plugins[ $basename ]['Author'] = (string) ( $branding['vendor_name'] ?? $plugins[ $basename ]['Author'] );
		}

		return $plugins;
	}

	/**
	 * Rewrite the top-level admin menu label.
	 *
	 * @return void
	 */
	public function rename_menu(): void {
		global $menu, $submenu;
		$name = $this->name();
		if ( ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as &$item ) {
			if ( isset( $item[2] ) && 'bookora' === $item[2] ) {
				$item[0] = $name;
				break;
			}
		}
		unset( $item );

		if ( isset( $submenu['bookora'][0][0] ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu['bookora'][0][0] = $name;
		}
	}

	/**
	 * Branding settings block.
	 *
	 * @return array<string, mixed>
	 */
	private function branding(): array {
		$branding = $this->settings->get( 'branding', array() );

		return is_array( $branding ) ? $branding : array();
	}
}
