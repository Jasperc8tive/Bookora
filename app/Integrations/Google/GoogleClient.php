<?php
/**
 * Google Calendar / OAuth HTTP client.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations\Google;

use Bookora\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over Google's OAuth2 and Calendar v3 HTTP endpoints.
 *
 * Network methods are config-gated; the event-payload mapping (`to_event`) is
 * pure and unit-tested.
 */
final class GoogleClient {

	private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	private const API_BASE  = 'https://www.googleapis.com/calendar/v3';
	private const SCOPE     = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly';

	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether the Google app credentials are configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->client_id() && '' !== $this->client_secret();
	}

	/**
	 * Build the OAuth consent URL.
	 *
	 * @param string $state        Opaque signed state.
	 * @param string $redirect_uri Redirect URI.
	 * @return string
	 */
	public function auth_url( string $state, string $redirect_uri ): string {
		return self::AUTH_URL . '?' . http_build_query(
			array(
				'client_id'     => $this->client_id(),
				'redirect_uri'  => $redirect_uri,
				'response_type' => 'code',
				'scope'         => self::SCOPE,
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			)
		);
	}

	/**
	 * Exchange an authorization code for tokens.
	 *
	 * @param string $code         Authorization code.
	 * @param string $redirect_uri Redirect URI.
	 * @return array<string, mixed>|WP_Error
	 */
	public function exchange_code( string $code, string $redirect_uri ): array|WP_Error {
		return $this->token_request(
			array(
				'code'          => $code,
				'client_id'     => $this->client_id(),
				'client_secret' => $this->client_secret(),
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			)
		);
	}

	/**
	 * Refresh an access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array<string, mixed>|WP_Error
	 */
	public function refresh( string $refresh_token ): array|WP_Error {
		return $this->token_request(
			array(
				'client_id'     => $this->client_id(),
				'client_secret' => $this->client_secret(),
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			)
		);
	}

	/**
	 * Insert an event; returns the created event id.
	 *
	 * @param string               $access_token Access token.
	 * @param string               $calendar_id  Calendar id.
	 * @param array<string, mixed> $event        Event body.
	 * @return string|WP_Error
	 */
	public function insert_event( string $access_token, string $calendar_id, array $event ): string|WP_Error {
		$res = $this->api( 'POST', $access_token, "/calendars/{$calendar_id}/events", $event );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return (string) ( $res['id'] ?? '' );
	}

	/**
	 * Update an existing event.
	 *
	 * @param string               $access_token Access token.
	 * @param string               $calendar_id  Calendar id.
	 * @param string               $event_id     Event id.
	 * @param array<string, mixed> $event        Event body.
	 * @return bool|WP_Error
	 */
	public function update_event( string $access_token, string $calendar_id, string $event_id, array $event ): bool|WP_Error {
		$res = $this->api( 'PUT', $access_token, "/calendars/{$calendar_id}/events/{$event_id}", $event );

		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * Delete an event.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id  Calendar id.
	 * @param string $event_id     Event id.
	 * @return bool|WP_Error
	 */
	public function delete_event( string $access_token, string $calendar_id, string $event_id ): bool|WP_Error {
		$res = $this->api( 'DELETE', $access_token, "/calendars/{$calendar_id}/events/{$event_id}", null );

		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * Free/busy intervals for a calendar in a UTC range.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id  Calendar id.
	 * @param string $from_iso     ISO8601 start.
	 * @param string $to_iso       ISO8601 end.
	 * @return array<int, array{start_utc: string, end_utc: string}>|WP_Error
	 */
	public function free_busy( string $access_token, string $calendar_id, string $from_iso, string $to_iso ): array|WP_Error {
		$res = $this->api(
			'POST',
			$access_token,
			'/freeBusy',
			array(
				'timeMin' => $from_iso,
				'timeMax' => $to_iso,
				'items'   => array( array( 'id' => $calendar_id ) ),
			),
			'https://www.googleapis.com/calendar/v3'
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$busy = (array) ( $res['calendars'][ $calendar_id ]['busy'] ?? array() );

		return array_map(
			static fn ( array $b ): array => array(
				'start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( (string) ( $b['start'] ?? 'now' ) ) ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( (string) ( $b['end'] ?? 'now' ) ) ),
			),
			$busy
		);
	}

	/**
	 * Map appointment context to a Google event body. Pure (no I/O).
	 *
	 * @param array{summary: string, start_utc: string, end_utc: string, description?: string} $context Context.
	 * @return array<string, mixed>
	 */
	public function to_event( array $context ): array {
		return array(
			'summary'     => (string) ( $context['summary'] ?? 'Booking' ),
			'description' => (string) ( $context['description'] ?? '' ),
			'start'       => array(
				'dateTime' => $this->iso( (string) $context['start_utc'] ),
				'timeZone' => 'UTC',
			),
			'end'         => array(
				'dateTime' => $this->iso( (string) $context['end_utc'] ),
				'timeZone' => 'UTC',
			),
		);
	}

	/**
	 * Convert a "Y-m-d H:i:s" UTC string to RFC3339.
	 *
	 * @param string $utc UTC datetime.
	 * @return string
	 */
	private function iso( string $utc ): string {
		return str_replace( ' ', 'T', $utc ) . 'Z';
	}

	/**
	 * Perform a token-endpoint request.
	 *
	 * @param array<string, string> $body Form body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $body ): array|WP_Error {
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $parsed ) || isset( $parsed['error'] ) ) {
			return new WP_Error( 'bookora_google_oauth', __( 'Google rejected the OAuth request.', 'bookora' ), array( 'status' => 502 ) );
		}

		return $parsed;
	}

	/**
	 * Perform a Calendar API request.
	 *
	 * @param string                    $method       HTTP method.
	 * @param string                    $access_token Access token.
	 * @param string                    $path         API path.
	 * @param array<string, mixed>|null $body         JSON body.
	 * @param string|null               $base         Optional base override.
	 * @return array<string, mixed>|WP_Error
	 */
	private function api( string $method, string $access_token, string $path, ?array $body, ?string $base = null ): array|WP_Error {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = wp_remote_request( ( $base ?? self::API_BASE ) . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return new WP_Error( 'bookora_google_api', __( 'Google Calendar API error.', 'bookora' ), array( 'status' => 502 ) );
		}

		$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * Google OAuth client id.
	 *
	 * @return string
	 */
	private function client_id(): string {
		$integrations = (array) $this->settings->get( 'integrations', array() );

		return (string) ( $integrations['google']['client_id'] ?? '' );
	}

	/**
	 * Google OAuth client secret.
	 *
	 * @return string
	 */
	private function client_secret(): string {
		$integrations = (array) $this->settings->get( 'integrations', array() );

		return (string) ( $integrations['google']['client_secret'] ?? '' );
	}
}
