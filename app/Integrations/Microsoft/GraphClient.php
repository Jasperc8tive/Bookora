<?php
/**
 * Microsoft Graph / OAuth HTTP client.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations\Microsoft;

use Bookora\Core\Settings;
use Bookora\Integrations\CalendarClient;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over Microsoft identity (OAuth2) and Graph Calendar endpoints.
 *
 * Network methods are config-gated; the event-payload mapping (`to_event`) is
 * pure and unit-tested.
 */
final class GraphClient implements CalendarClient {

	private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
	private const SCOPE      = 'offline_access Calendars.ReadWrite';

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
	 * {@inheritDoc}
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
		return $this->authority() . '/oauth2/v2.0/authorize?' . http_build_query(
			array(
				'client_id'     => $this->client_id(),
				'response_type' => 'code',
				'redirect_uri'  => $redirect_uri,
				'response_mode' => 'query',
				'scope'         => self::SCOPE,
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
				'client_id'     => $this->client_id(),
				'scope'         => self::SCOPE,
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
				'client_secret' => $this->client_secret(),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function refresh( string $refresh_token ): array|WP_Error {
		return $this->token_request(
			array(
				'client_id'     => $this->client_id(),
				'scope'         => self::SCOPE,
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
				'client_secret' => $this->client_secret(),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_event( string $access_token, string $calendar_id, array $event ): string|WP_Error {
		unset( $calendar_id );
		$res = $this->api( 'POST', $access_token, '/me/events', $event );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return (string) ( $res['id'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_event( string $access_token, string $calendar_id, string $event_id, array $event ): bool|WP_Error {
		unset( $calendar_id );
		$res = $this->api( 'PATCH', $access_token, '/me/events/' . rawurlencode( $event_id ), $event );

		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_event( string $access_token, string $calendar_id, string $event_id ): bool|WP_Error {
		unset( $calendar_id );
		$res = $this->api( 'DELETE', $access_token, '/me/events/' . rawurlencode( $event_id ), null );

		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Uses calendarView and maps each event to a busy interval.
	 */
	public function free_busy( string $access_token, string $calendar_id, string $from_iso, string $to_iso ): array|WP_Error {
		unset( $calendar_id );
		$query = http_build_query(
			array(
				'startDateTime' => $from_iso,
				'endDateTime'   => $to_iso,
				'$select'       => 'start,end',
				'$top'          => 200,
			)
		);
		$res   = $this->api( 'GET', $access_token, '/me/calendarView?' . $query, null, array( 'Prefer' => 'outlook.timezone="UTC"' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$intervals = array();
		foreach ( (array) ( $res['value'] ?? array() ) as $event ) {
			$start = (string) ( $event['start']['dateTime'] ?? '' );
			$end   = (string) ( $event['end']['dateTime'] ?? '' );
			if ( '' === $start || '' === $end ) {
				continue;
			}
			$intervals[] = array(
				'start_utc' => gmdate( 'Y-m-d H:i:s', (int) strtotime( $start . ' UTC' ) ),
				'end_utc'   => gmdate( 'Y-m-d H:i:s', (int) strtotime( $end . ' UTC' ) ),
			);
		}

		return $intervals;
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_event( array $context ): array {
		return array(
			'subject' => (string) ( $context['summary'] ?? 'Booking' ),
			'body'    => array(
				'contentType' => 'Text',
				'content'     => (string) ( $context['description'] ?? '' ),
			),
			'start'   => array(
				'dateTime' => str_replace( ' ', 'T', (string) $context['start_utc'] ),
				'timeZone' => 'UTC',
			),
			'end'     => array(
				'dateTime' => str_replace( ' ', 'T', (string) $context['end_utc'] ),
				'timeZone' => 'UTC',
			),
		);
	}

	/**
	 * Perform a token-endpoint request.
	 *
	 * @param array<string, string> $body Form body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function token_request( array $body ): array|WP_Error {
		$response = wp_remote_post(
			$this->authority() . '/oauth2/v2.0/token',
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
			return new WP_Error( 'bookora_ms_oauth', __( 'Microsoft rejected the OAuth request.', 'bookora' ), array( 'status' => 502 ) );
		}

		return $parsed;
	}

	/**
	 * Perform a Graph API request.
	 *
	 * @param string                    $method       HTTP method.
	 * @param string                    $access_token Access token.
	 * @param string                    $path         API path.
	 * @param array<string, mixed>|null $body         JSON body.
	 * @param array<string, string>     $headers      Extra headers.
	 * @return array<string, mixed>|WP_Error
	 */
	private function api( string $method, string $access_token, string $path, ?array $body, array $headers = array() ): array|WP_Error {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array_merge(
				array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				$headers
			),
		);
		if ( null !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = wp_remote_request( self::GRAPH_BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
			return new WP_Error( 'bookora_ms_api', __( 'Microsoft Graph API error.', 'bookora' ), array( 'status' => 502 ) );
		}

		$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * The tenant-scoped authority URL.
	 *
	 * @return string
	 */
	private function authority(): string {
		$tenant = $this->config( 'tenant' );

		return 'https://login.microsoftonline.com/' . ( '' !== $tenant ? rawurlencode( $tenant ) : 'common' );
	}

	/**
	 * Microsoft OAuth client id.
	 *
	 * @return string
	 */
	private function client_id(): string {
		return $this->config( 'client_id' );
	}

	/**
	 * Microsoft OAuth client secret.
	 *
	 * @return string
	 */
	private function client_secret(): string {
		return $this->config( 'client_secret' );
	}

	/**
	 * Read a value from settings.integrations.microsoft.
	 *
	 * @param string $key Config key.
	 * @return string
	 */
	private function config( string $key ): string {
		$integrations = (array) $this->settings->get( 'integrations', array() );

		return (string) ( $integrations['microsoft'][ $key ] ?? '' );
	}
}
