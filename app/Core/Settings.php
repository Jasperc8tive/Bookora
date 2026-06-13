<?php
/**
 * Settings framework.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Typed wrapper over a single autoloaded `bookora_settings` option.
 *
 * Centralises defaults and per-key sanitisation so feature code never touches
 * the raw option array.
 */
class Settings {

	/**
	 * Option name in wp_options.
	 */
	private const OPTION = 'bookora_settings';

	/**
	 * In-request cache of the settings array.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Default values for every known setting.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'business_name'            => '',
			'timezone'                 => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			'currency'                 => 'NGN',
			'date_format'              => 'Y-m-d',
			'time_format'              => 'H:i',
			'week_starts_on'           => 1,
			'log_level'                => 'error',
			'delete_data_on_uninstall' => false,
			'payments'                 => array(
				'paystack'    => array(
					'enabled'    => false,
					'public_key' => '',
					'secret_key' => '',
				),
				'flutterwave' => array(
					'enabled'     => false,
					'public_key'  => '',
					'secret_key'  => '',
					'secret_hash' => '',
				),
				'stripe'      => array(
					'enabled'         => false,
					'publishable_key' => '',
					'secret_key'      => '',
					'webhook_secret'  => '',
				),
			),
			'notifications'            => array(
				'channels'  => array(
					'email'    => array( 'enabled' => true ),
					'sms'      => array(
						'enabled' => false,
						'api_key' => '',
						'sender'  => '',
					),
					'whatsapp' => array(
						'enabled'         => false,
						'phone_number_id' => '',
						'access_token'    => '',
					),
					'push'     => array(
						'enabled'  => false,
						'endpoint' => '',
						'api_key'  => '',
					),
				),
				'reminders' => array( 'offsets' => array( 1440, 60 ) ),
				'templates' => array(),
			),
		);
	}

	/**
	 * Retrieve a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is absent.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		$defaults = $this->defaults();
		if ( array_key_exists( $key, $defaults ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Update a single setting (sanitised) and persist.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public function set( string $key, mixed $value ): void {
		$all         = $this->all();
		$all[ $key ] = $this->sanitize( $key, $value );
		$this->save( $all );
	}

	/**
	 * Merge and persist multiple settings.
	 *
	 * @param array<string, mixed> $values Key => value.
	 * @return void
	 */
	public function update( array $values ): void {
		$all = $this->all();
		foreach ( $values as $key => $value ) {
			$all[ $key ] = $this->sanitize( $key, $value );
		}
		$this->save( $all );
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = is_array( $stored ) ? $stored : array();
		}

		return array_merge( $this->defaults(), $this->cache );
	}

	/**
	 * Persist default values for any keys not yet stored. Idempotent.
	 *
	 * @return void
	 */
	public function seed_defaults(): void {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			$this->save( $this->defaults() );
			return;
		}

		$merged = array_merge( $this->defaults(), $stored );
		$this->save( $merged );
	}

	/**
	 * Persist the settings array (autoloaded).
	 *
	 * @param array<string, mixed> $values Full settings array.
	 * @return void
	 */
	private function save( array $values ): void {
		$this->cache = $values;
		update_option( self::OPTION, $values, true );
	}

	/**
	 * Sanitise a value according to its key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	private function sanitize( string $key, mixed $value ): mixed {
		switch ( $key ) {
			case 'delete_data_on_uninstall':
				return (bool) $value;
			case 'week_starts_on':
				return max( 0, min( 6, (int) $value ) );
			case 'log_level':
				$allowed = array( 'debug', 'info', 'warning', 'error' );
				$value   = is_string( $value ) ? strtolower( $value ) : 'error';
				return in_array( $value, $allowed, true ) ? $value : 'error';
			case 'currency':
				return strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $value ), 0, 3 ) );
			default:
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}
	}
}
