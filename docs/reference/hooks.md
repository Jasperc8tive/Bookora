# Bookora — Hooks Reference

Every extension point Bookora exposes. Actions let you react to events; filters let
you change values. All hook names are prefixed `bookora_`.

## Actions

| Hook | Fired when | Arguments |
|---|---|---|
| `bookora_booking_created` | A booking (or recurring set) is created | `int $appointment_id, array $context` |
| `bookora_booking_rescheduled` | A booking is moved | `int $appointment_id, array $context` |
| `bookora_booking_cancelled` | A booking is cancelled | `int $appointment_id` |
| `bookora_payment_succeeded` | A payment is confirmed (webhook-authoritative) | `int $payment_id, array $context` |
| `bookora_waitlist_opening` | A slot opens and a waitlist entry can be promoted | `array $entry` |
| `bookora_register_channels` | Notification channels are being registered | `ChannelRegistry $registry` |
| `bookora_register_gateways` | Payment gateways are being registered | `GatewayRegistry $registry` |

### Example — post to Slack when a booking is created

```php
add_action( 'bookora_booking_created', function ( int $appointment_id ): void {
    wp_remote_post( 'https://hooks.slack.com/...', array(
        'body' => wp_json_encode( array( 'text' => "New booking #{$appointment_id}" ) ),
    ) );
} );
```

## Filters

| Hook | Filters | Arguments |
|---|---|---|
| `bookora_rest_controllers` | The list of REST controller classes to register | `array $controllers` |
| `bookora_external_busy` | External (calendar) busy intervals merged into availability | `array $busy, int $staff_id, string $from, string $to` |
| `bookora_slot_score` | A candidate slot's suggestion score (AI scheduling) | `float $score, array $slot, array $context` |
| `bookora_feature_enabled` | Whether a feature is enabled (final say / kill-switch) | `bool $enabled, string $feature, string $tier` |
| `bookora_admin_data` | Data localised into `window.BookoraAdmin` (branding lives here) | `array $data` |
| `bookora_telemetry_payload` | The anonymised telemetry snapshot before sending | `array $payload` |
| `bookora_license_api_url` | License-server endpoint (empty disables remote checks) | `string $url` |
| `bookora_update_api_url` | Update-server endpoint (empty disables update checks) | `string $url` |
| `bookora_telemetry_api_url` | Telemetry endpoint (empty disables sending) | `string $url` |

### Example — plug in a Claude-powered slot scorer

```php
add_filter( 'bookora_slot_score', function ( float $score, array $slot, array $context ): float {
    // Re-rank with your own model; return a higher score for better slots.
    return $score + my_model_score( $slot, $context );
}, 10, 3 );
```

### Example — point the updater at your own server

```php
add_filter( 'bookora_update_api_url', fn () => 'https://updates.example.com/bookora' );
add_filter( 'bookora_license_api_url', fn () => 'https://licenses.example.com/bookora' );
```

### Example — disable a feature regardless of license tier

```php
add_filter( 'bookora_feature_enabled', function ( bool $on, string $feature ): bool {
    return 'ai_scheduling' === $feature ? false : $on;
}, 10, 2 );
```

## Scheduled events (WP-Cron)

These hooks are scheduled by Bookora; you can unschedule or re-time them if needed.

| Hook | Cadence | Purpose |
|---|---|---|
| `bookora_notify` | on-demand (async) | Deliver a queued notification |
| `bookora_send_reminder` | on-demand (async) | Send a booking reminder at its offset |
| `bookora_membership_renewals` | daily | Process membership/subscription renewals |
| `bookora_license_refresh` | daily | Re-validate the license against the server |
| `bookora_telemetry_send` | weekly | Send the telemetry snapshot (only when opted in) |
