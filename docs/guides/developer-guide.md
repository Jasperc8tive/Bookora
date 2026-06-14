# Bookora — Developer Guide

## Architecture at a glance

* **PHP 8.2+, WordPress 6.8+**, REST-first, with **custom database tables**
  (`wp_bkra_*`) — never posts/postmeta.
* **Domain-Driven Design** under `app/`: each domain is a module with a Repository
  (data), a Manager/Engine (logic), a Controller (REST), and a ServiceProvider (DI
  wiring).
* A small **PSR-11 container** (`app/Core/Container.php`) with constructor
  autowiring boots service providers listed in `app/Core/Plugin.php`.
* The admin is a **React 18 + TypeScript + Vite + Tailwind** SPA; the front-end
  wizard and customer portal are separate Vite entries sharing a code-split runtime.

```
bookora.php            Bootstrap: constants, env guard, lifecycle hooks
app/
  Core/                Plugin, Container, Settings, Logger, Activator/Deactivator/Uninstaller
  Database/            MigrationRunner, Schema, Repository/AbstractRepository, Migrations/
  API/                 Router, AbstractController, Controllers/
  Security/            Capabilities, Roles, PermissionMatrix, ActivityLogger, Crypto, RateLimiter
  Services/ Staff/ Customers/ Appointments/   Core booking domains
  Payments/ Notifications/ Integrations/       Money, messaging, calendar sync
  Portal/ Reports/ Elementor/                  Customer portal, analytics, page-builder
  Coupons/ GiftCards/ Memberships/ Resources/ Waitlist/   Advanced features
  Scheduling/          AI scheduling (SlotScorer, SchedulingIntelligence)
  Licensing/ Updates/ Telemetry/ Branding/ DataTransfer/ Commercial/   Commercial hardening
  Admin/               Menu, Assets, DashboardPage
assets/src/            React sources (admin / frontend / portal) → assets/build/
tests/                 phpunit/ (WP integration) + js/ (Jest)
docs/                  Specs, stage audits, guides, references
```

## Service providers

Providers implement `app/Core/Contracts/ServiceProvider` (`register()` then
`boot()`) and are registered in order in `app/Core/Plugin.php`. To add a domain:

1. Create `MyThingRepository` (extend `AbstractRepository`), a manager, and a
   `MyThingController` (extend `AbstractController`).
2. Create `MyThingServiceProvider`: bind singletons in `register()`, then append
   your controller to the `bookora_rest_controllers` filter.
3. Add the provider class to the `$providers` array in `Plugin.php`.

REST controllers self-register via the filter, so the Router discovers them
without edits.

## Database & migrations

* `Schema` resolves prefixed names (`$wpdb->prefix . 'bkra_' . $name`) and provides
  `charset_collate()` / `table_exists()`.
* `MigrationRunner` records applied versions in `wp_bkra_migrations` and runs
  pending `Migration_NNNN_*` classes. Migrations are idempotent (`dbDelta`) and
  reversible; foreign keys are added via raw `ALTER TABLE` (InnoDB).
* Tables: `services, staff, staff_availability, customers, appointments, payments,
  notes, notifications, waitlist, resources, integrations, activity_logs` (0001),
  plus `service_categories` (0002), `staff_services` (0003), `booking_holds` (0004),
  `appointments.external_ids` (0005), and `coupons, gift_cards, memberships,
  customer_memberships` (0006).

Every table has `id`, timestamps, and a soft-delete `deleted_at`. The
`AbstractRepository` excludes soft-deleted rows by default and uses prepared
statements with a column allowlist.

## Authorization

Capabilities are defined in `app/Security/Capabilities.php` and mapped to roles by
`PermissionMatrix`:

| Capability | Manager | Staff | Customer | Admin |
|---|:--:|:--:|:--:|:--:|
| `bookora_manage_settings` | | | | ✓ |
| `bookora_manage_services` | ✓ | | | ✓ |
| `bookora_manage_staff` | ✓ | | | ✓ |
| `bookora_manage_customers` | ✓ | | | ✓ |
| `bookora_manage_bookings` | ✓ | | | ✓ |
| `bookora_manage_payments` | ✓ | | | ✓ |
| `bookora_view_reports` | ✓ | | | ✓ |
| `bookora_view_audit_log` | ✓ | | | ✓ |
| `bookora_manage_agency` | | | | ✓ |
| `bookora_manage_affiliates` | | | | ✓ |
| `bookora_view_own_schedule` | ✓ | ✓ | | ✓ |
| `bookora_manage_own_bookings` | ✓ | ✓ | ✓ | ✓ |
| `bookora_access_portal` | | | ✓ | ✓ |

> The Administrator gets every capability. Note that `manage_settings`,
> `manage_agency`, and `manage_affiliates` are Administrator-only — the Bookora
> Manager role runs day-to-day operations but not plugin-wide settings.

Roles: `bookora_manager`, `bookora_staff`, `bookora_customer`. Every REST route
calls `require_capability()`; the customer portal additionally uses a stateless
HMAC token and re-checks ownership on every action.

## Security model

* Prepared statements everywhere; table identifiers come from `Schema`, never user
  input (PHPCS-annotated).
* Secrets (OAuth tokens, license key) encrypted at rest with AES-256-GCM
  (`Security/Crypto`); settings responses mask secrets.
* Admin REST uses cookie auth + `X-WP-Nonce`; capability checks on every route.
* `ActivityLogger` writes a SHA-256 hash-chained audit log with HMAC'd IP/UA.
* Protected upload dirs (`bookora-logs/`, `bookora-backups/`) deny direct web access.

## Extending Bookora

* **React/scheduling/payments/notifications** all expose registries or filters — see
  the [hooks reference](../reference/hooks.md). Notable seams: `bookora_slot_score`
  (AI scoring), `bookora_register_gateways` / `bookora_register_channels`,
  `bookora_external_busy` (availability), `bookora_feature_enabled` (flags).
* **Commercial endpoints** (`bookora_license_api_url`, `bookora_update_api_url`,
  `bookora_telemetry_api_url`) are filterable and empty by default.

## Local development

```bash
composer install
composer phpcs            # WordPress-Extra + PSR-12 + PHPCompat 8.2
composer phpstan          # level 6 + WP stubs
composer test             # PHPUnit — needs the WP test library + MySQL

npm install
npm run build             # tsc --noEmit && vite build
npm run lint              # eslint, zero warnings
npm run test              # jest
```

The PHPUnit suite needs the WordPress test library and a throwaway MySQL database
(`bin/install-wp-tests.sh`); it runs in CI. PHPCS, PHPStan, ESLint, Jest, and the
Vite build run anywhere.
