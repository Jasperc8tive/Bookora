<?php
/**
 * Security service provider.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

use Bookora\Core\Container;
use Bookora\Core\Contracts\ServiceProvider;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use WP_REST_Response;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Wires roles, guards, rate limiting, and audit logging into the plugin.
 */
final class SecurityServiceProvider implements ServiceProvider {

	/**
	 * Default REST rate-limit: requests allowed per window, per client IP.
	 */
	private const REST_MAX_REQUESTS = 120;

	/**
	 * Default REST rate-limit window, in seconds.
	 */
	private const REST_WINDOW = 60;

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
		$container->singleton( Guard::class );
		$container->singleton( Nonce::class );
		$container->singleton( RateLimiter::class );
		$container->singleton( Roles::class );

		$container->singleton(
			AuditLogRepository::class,
			static fn ( Container $c ): AuditLogRepository => new AuditLogRepository( null, $c->get( Schema::class ) )
		);
		$container->singleton(
			ActivityLogger::class,
			static fn ( Container $c ): ActivityLogger => new ActivityLogger( $c->get( AuditLogRepository::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		// Keep roles/capabilities in sync after upgrades.
		add_action(
			'admin_init',
			static function () use ( $container ): void {
				$container->get( Roles::class )->sync();
			}
		);

		$this->register_rate_limiting( $container->get( RateLimiter::class ) );
		$this->register_audit_hooks( $container->get( ActivityLogger::class ) );
	}

	/**
	 * Apply a per-IP fixed-window limit to all bookora/v1 routes.
	 *
	 * @param RateLimiter $limiter Rate limiter.
	 * @return void
	 */
	private function register_rate_limiting( RateLimiter $limiter ): void {
		add_filter(
			'rest_pre_dispatch',
			static function ( $result, $server, $request ) use ( $limiter ) {
				unset( $server );

				// Respect an earlier short-circuit.
				if ( null !== $result ) {
					return $result;
				}

				$route = (string) $request->get_route();
				if ( 0 !== strpos( $route, '/bookora/' ) ) {
					return $result;
				}

				$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
				$state = $limiter->hit( 'rest:' . $ip, self::REST_MAX_REQUESTS, self::REST_WINDOW );

				if ( $state['allowed'] ) {
					return $result;
				}

				$response = new WP_REST_Response(
					array(
						'success' => false,
						'code'    => 'bookora_rate_limited',
						'message' => __( 'Too many requests. Please slow down.', 'bookora' ),
					),
					429
				);
				$response->header( 'Retry-After', (string) $state['retry_after'] );

				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Record security-relevant WordPress events to the audit log.
	 *
	 * @param ActivityLogger $logger Audit logger.
	 * @return void
	 */
	private function register_audit_hooks( ActivityLogger $logger ): void {
		add_action(
			'wp_login',
			static function ( $user_login, $user ) use ( $logger ): void {
				$actor_id = $user instanceof WP_User ? $user->ID : null;
				$logger->log(
					'auth.login',
					array(
						'actor_type'  => 'user',
						'actor_id'    => $actor_id,
						'entity_type' => 'user',
						'entity_id'   => $actor_id,
					)
				);
			},
			10,
			2
		);

		add_action(
			'set_user_role',
			static function ( $user_id, $role, $old_roles ) use ( $logger ): void {
				$logger->log(
					'user.role_changed',
					array(
						'entity_type' => 'user',
						'entity_id'   => (int) $user_id,
						'context'     => array(
							'new_role'  => is_string( $role ) ? $role : '',
							'old_roles' => is_array( $old_roles ) ? implode( ',', $old_roles ) : '',
						),
					)
				);
			},
			10,
			3
		);

		add_action(
			'update_option_bookora_settings',
			static function () use ( $logger ): void {
				$logger->log( 'settings.changed', array( 'entity_type' => 'settings' ) );
			}
		);
	}
}
