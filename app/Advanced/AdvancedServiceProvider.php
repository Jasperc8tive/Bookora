<?php
/**
 * Advanced features module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Advanced;

use Bookora\API\Controllers\AdvancedController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Coupons\CouponManager;
use Bookora\Coupons\CouponRepository;
use Bookora\Customers\CustomerManager;
use Bookora\GiftCards\GiftCardManager;
use Bookora\GiftCards\GiftCardRepository;
use Bookora\Memberships\CustomerMembershipRepository;
use Bookora\Memberships\MembershipManager;
use Bookora\Memberships\MembershipRepository;
use Bookora\Resources\ResourceManager;
use Bookora\Resources\ResourceRepository;
use Bookora\Security\ActivityLogger;
use Bookora\Waitlist\WaitlistManager;
use Bookora\Waitlist\WaitlistRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires coupons, gift cards, memberships/subscriptions, resources, and the
 * waitlist (with promote-on-cancel + renewal cron) and their REST controller.
 */
final class AdvancedServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$audit = static fn ( Container $c ): ActivityLogger => $c->get( ActivityLogger::class );

		$container->singleton(
			CouponManager::class,
			static fn ( Container $c ): CouponManager => new CouponManager( $c->get( CouponRepository::class ), $c->get( ActivityLogger::class ) )
		);
		$container->singleton(
			GiftCardManager::class,
			static fn ( Container $c ): GiftCardManager => new GiftCardManager( $c->get( GiftCardRepository::class ), $c->get( ActivityLogger::class ) )
		);
		$container->singleton(
			MembershipManager::class,
			static fn ( Container $c ): MembershipManager => new MembershipManager(
				$c->get( MembershipRepository::class ),
				$c->get( CustomerMembershipRepository::class ),
				$c->get( ActivityLogger::class )
			)
		);
		$container->singleton(
			ResourceManager::class,
			static fn ( Container $c ): ResourceManager => new ResourceManager( $c->get( ResourceRepository::class ), $c->get( ActivityLogger::class ) )
		);
		$container->singleton(
			WaitlistManager::class,
			static fn ( Container $c ): WaitlistManager => new WaitlistManager(
				$c->get( WaitlistRepository::class ),
				$c->get( AppointmentRepository::class ),
				$c->get( CustomerManager::class ),
				$c->get( ActivityLogger::class )
			)
		);
		unset( $audit );

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = AdvancedController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		// Promote the waitlist when a booking is cancelled.
		add_action(
			'bookora_booking_cancelled',
			static function ( int $appointment_id ) use ( $container ): void {
				$container->get( WaitlistManager::class )->promote_for_appointment( $appointment_id );
			}
		);

		// Daily membership/subscription renewal sweep.
		add_action(
			'bookora_membership_renewals',
			static function () use ( $container ): void {
				$container->get( MembershipManager::class )->process_renewals();
			}
		);
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'bookora_membership_renewals' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bookora_membership_renewals' );
		}
	}
}
