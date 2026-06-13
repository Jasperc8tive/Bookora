<?php
/**
 * Shared channel behaviour.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications\Channels;

use Bookora\Core\Settings;
use Bookora\Notifications\Contracts\NotificationChannel;

defined( 'ABSPATH' ) || exit;

/**
 * Base class giving channels settings access.
 */
abstract class AbstractChannel implements NotificationChannel {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * This channel's config block from settings.notifications.channels.
	 *
	 * @return array<string, mixed>
	 */
	protected function config(): array {
		$notifications = (array) $this->settings->get( 'notifications', array() );
		$channels      = (array) ( $notifications['channels'] ?? array() );

		return (array) ( $channels[ $this->key() ] ?? array() );
	}

	/**
	 * Whether this channel is enabled in settings.
	 *
	 * @return bool
	 */
	protected function enabled(): bool {
		$config = $this->config();

		return ! empty( $config['enabled'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function recipient_field(): string {
		return 'customer_email';
	}
}
