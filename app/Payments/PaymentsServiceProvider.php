<?php
/**
 * Payments module service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments;

use Bookora\API\Controllers\PaymentsController;
use Bookora\API\Controllers\PaymentWebhookController;
use Bookora\API\Controllers\PublicPaymentController;
use Bookora\Appointments\AppointmentRepository;
use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerRepository;
use Bookora\Payments\Gateways\FlutterwaveDriver;
use Bookora\Payments\Gateways\PaystackDriver;
use Bookora\Payments\Gateways\StripeDriver;
use Bookora\Security\ActivityLogger;
use Bookora\Services\ServiceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wires gateways, the payment manager, and the payment REST controllers.
 */
final class PaymentsServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( PaymentRepository::class );

		$container->singleton(
			GatewayRegistry::class,
			static function ( Container $c ): GatewayRegistry {
				$settings = $c->get( Settings::class );
				$registry = new GatewayRegistry();
				$registry->add( new PaystackDriver( $settings ) );
				$registry->add( new FlutterwaveDriver( $settings ) );
				$registry->add( new StripeDriver( $settings ) );

				/**
				 * Allow add-ons to register additional payment gateways.
				 *
				 * @param GatewayRegistry $registry The gateway registry.
				 */
				do_action( 'bookora_register_gateways', $registry );

				return $registry;
			}
		);

		$container->singleton(
			PaymentManager::class,
			static fn ( Container $c ): PaymentManager => new PaymentManager(
				$c->get( PaymentRepository::class ),
				$c->get( AppointmentRepository::class ),
				$c->get( ServiceRepository::class ),
				$c->get( CustomerRepository::class ),
				$c->get( GatewayRegistry::class ),
				$c->get( Settings::class ),
				$c->get( ActivityLogger::class )
			)
		);

		add_filter(
			'bookora_rest_controllers',
			static function ( array $controllers ): array {
				$controllers[] = PaymentsController::class;
				$controllers[] = PaymentWebhookController::class;
				$controllers[] = PublicPaymentController::class;

				return $controllers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		unset( $container );
	}
}
