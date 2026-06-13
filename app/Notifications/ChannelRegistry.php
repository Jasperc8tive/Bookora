<?php
/**
 * Channel registry.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications;

use Bookora\Notifications\Contracts\NotificationChannel;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the available notification channels keyed by their machine key.
 */
final class ChannelRegistry {

	/**
	 * Registered channels.
	 *
	 * @var array<string, NotificationChannel>
	 */
	private array $channels = array();

	/**
	 * Register a channel.
	 *
	 * @param NotificationChannel $channel Channel.
	 * @return void
	 */
	public function add( NotificationChannel $channel ): void {
		$this->channels[ $channel->key() ] = $channel;
	}

	/**
	 * Resolve a channel by key.
	 *
	 * @param string $key Channel key.
	 * @return NotificationChannel|null
	 */
	public function get( string $key ): ?NotificationChannel {
		return $this->channels[ $key ] ?? null;
	}

	/**
	 * All channels.
	 *
	 * @return array<string, NotificationChannel>
	 */
	public function all(): array {
		return $this->channels;
	}

	/**
	 * Only the enabled/configured channels.
	 *
	 * @return array<string, NotificationChannel>
	 */
	public function configured(): array {
		return array_filter( $this->channels, static fn ( NotificationChannel $c ): bool => $c->is_configured() );
	}
}
