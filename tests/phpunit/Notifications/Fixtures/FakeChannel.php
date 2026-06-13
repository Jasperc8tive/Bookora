<?php
/**
 * Test double notification channel.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Notifications\Fixtures;

use Bookora\Notifications\Contracts\NotificationChannel;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Captures sent messages in memory for assertions.
 */
final class FakeChannel implements NotificationChannel {

	/**
	 * Captured sends.
	 *
	 * @var array<int, array{recipient: string, message: array{subject: string, body: string}}>
	 */
	public array $sent = array();

	public bool $configured = true;
	public bool $fail       = false;

	public function key(): string {
		return 'fake';
	}

	public function label(): string {
		return 'Fake';
	}

	public function is_configured(): bool {
		return $this->configured;
	}

	public function recipient_field(): string {
		return 'customer_email';
	}

	public function send( string $recipient, array $message, array $context ): array|WP_Error {
		unset( $context );
		if ( $this->fail ) {
			return new WP_Error( 'boom', 'failed to send' );
		}
		$this->sent[] = array(
			'recipient' => $recipient,
			'message'   => $message,
		);

		return array( 'provider_ref' => 'ref_' . count( $this->sent ) );
	}
}
