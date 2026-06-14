<?php
/**
 * Commercial hardening service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Commercial;

use Bookora\API\Controllers\CommercialController;
use Bookora\Branding\WhiteLabel;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Core\Settings;
use Bookora\Database\Schema;
use Bookora\DataTransfer\BackupManager;
use Bookora\DataTransfer\DataPortability;
use Bookora\Licensing\FeatureFlags;
use Bookora\Licensing\LicenseManager;
use Bookora\Security\ActivityLogger;
use Bookora\Security\Crypto;
use Bookora\Telemetry\Telemetry;
use Bookora\Updates\Updater;

defined( 'ABSPATH' ) || exit;

/**
 * Wires licensing, feature flags, the self-hosted updater, opt-in telemetry,
 * white-labelling, and data import/export + backups — plus the cron jobs and
 * update hooks that keep them running.
 */
final class CommercialServiceProvider implements ServiceProvider {

	private const LICENSE_CRON   = 'bookora_license_refresh';
	private const TELEMETRY_CRON = 'bookora_telemetry_send';

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			LicenseManager::class,
			static fn ( Container $c ): LicenseManager => new LicenseManager( $c->get( Crypto::class ), $c->get( ActivityLogger::class ) )
		);
		$container->singleton(
			FeatureFlags::class,
			static fn ( Container $c ): FeatureFlags => new FeatureFlags( $c->get( LicenseManager::class ), $c->get( Settings::class ) )
		);
		$container->singleton(
			Updater::class,
			static fn ( Container $c ): Updater => new Updater( $c->get( LicenseManager::class ) )
		);
		$container->singleton(
			Telemetry::class,
			static fn ( Container $c ): Telemetry => new Telemetry( $c->get( Settings::class ), $c->get( Schema::class ) )
		);
		$container->singleton(
			WhiteLabel::class,
			static fn ( Container $c ): WhiteLabel => new WhiteLabel( $c->get( Settings::class ), $c->get( FeatureFlags::class ) )
		);
		$container->singleton(
			DataPortability::class,
			static fn ( Container $c ): DataPortability => new DataPortability(
				$c->get( Schema::class ),
				$c->get( Settings::class ),
				$c->get( ActivityLogger::class )
			)
		);
		$container->singleton(
			BackupManager::class,
			static fn ( Container $c ): BackupManager => new BackupManager( $c->get( DataPortability::class ), $c->get( ActivityLogger::class ) )
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = CommercialController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		// Plugin updates + branding apply in admin contexts.
		if ( is_admin() ) {
			$container->get( Updater::class )->init();
		}
		$container->get( WhiteLabel::class )->init();

		// Daily license re-validation.
		add_action(
			self::LICENSE_CRON,
			static function () use ( $container ): void {
				$container->get( LicenseManager::class )->refresh();
			}
		);
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::LICENSE_CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::LICENSE_CRON );
		}

		// Weekly telemetry (only fires when opted in).
		add_action(
			self::TELEMETRY_CRON,
			static function () use ( $container ): void {
				$container->get( Telemetry::class )->send();
			}
		);
		$container->get( Telemetry::class )->sync_schedule();
	}
}
