# Bookora — Database Design

**Stage:** -1 · **Status:** Build complete · **Date:** 2026-06-05
Engine: MySQL 8 / MariaDB 10.6+, InnoDB, `utf8mb4`. Prefix: `wp_bkra_`.
Decision D-006: **custom tables**, not CPT/postmeta (time-series booking volume).

---

## 1. Entity-Relationship Diagram (logical)

```
 tenants ──1:N── locations
    │  │
    │  └─1:N── staff ──N:M── services   (via staff_services)
    │             │
    │             └─1:N── staff_schedules / staff_time_off / staff_calendar_tokens
    │
    ├─1:N── services ──1:N── service_options / intake_fields
    ├─1:N── customers ──1:N── bookings
    │                            │
 services ─1:N─┐                 ├─1:N── payments
 staff ───1:N─┤                 ├─1:N── booking_intake_answers
 locations 1:N┴──▶ bookings ────┼─1:N── notifications
                                 └─1:N── booking_events (audit/state)
    ├─1:N── coupons ──N:M── bookings (via booking_coupons)
    ├─1:N── waitlist_entries
    ├─1:N── affiliates ──1:N── affiliate_clicks / affiliate_commissions / affiliate_payouts
    ├─1:N── licenses / entitlements
    └─1:N── audit_log
```

## 2. Core Tables (selected columns)

> Every business table carries `tenant_id` (multi-tenant/agency scoping) + `created_at`, `updated_at`, `deleted_at` (soft delete).

**`wp_bkra_tenants`** — `id`, `name`, `slug`, `timezone`, `currency`, `white_label_json`, `status`.

**`wp_bkra_locations`** — `id`, `tenant_id`, `name`, `type` (physical|virtual), `address`, `lat`, `lng`, `meeting_provider`.

**`wp_bkra_services`** — `id`, `tenant_id`, `category_id`, `name`, `duration_min`, `buffer_before_min`, `buffer_after_min`, `price`, `deposit_type`, `deposit_value`, `capacity`, `min_notice_min`, `max_notice_min`, `lead_time_min`, `is_recurring_allowed`, `status`.

**`wp_bkra_staff`** — `id`, `tenant_id`, `wp_user_id`, `display_name`, `email`, `phone`, `bio`, `avatar`, `status`.

**`wp_bkra_staff_services`** — `staff_id`, `service_id`, `price_override`, `duration_override` (PK composite).

**`wp_bkra_staff_schedules`** — `id`, `staff_id`, `location_id`, `weekday`, `start_time`, `end_time`, `break_json`, `valid_from`, `valid_to`.

**`wp_bkra_staff_time_off`** — `id`, `staff_id`, `start_at`, `end_at`, `reason`, `status`.

**`wp_bkra_staff_calendar_tokens`** — `id`, `staff_id`, `provider`, `access_token_enc`, `refresh_token_enc`, `expires_at`, `sync_state`.

**`wp_bkra_customers`** — `id`, `tenant_id`, `wp_user_id?`, `name`, `email`, `phone`, `timezone`, `locale`, `consent_json`, `notes`.

**`wp_bkra_bookings`** — `id`, `tenant_id`, `customer_id`, `service_id`, `staff_id`, `location_id`, `start_at` (UTC), `end_at` (UTC), `status`, `price`, `tax`, `total`, `amount_paid`, `balance_due`, `source`, `affiliate_id?`, `parent_recurring_id?`, `external_calendar_event_id?`, `idempotency_key`.

**`wp_bkra_booking_holds`** — `id`, `service_id`, `staff_id`, `start_at`, `end_at`, `session_token`, `expires_at` (TTL soft-hold to prevent races).

**`wp_bkra_payments`** — `id`, `tenant_id`, `booking_id`, `gateway`, `gateway_ref`, `amount`, `currency`, `status`, `type` (full|deposit|refund), `webhook_verified`, `idempotency_key`, `raw_payload_enc`.

**`wp_bkra_notifications`** — `id`, `tenant_id`, `booking_id?`, `channel`, `event`, `template_id`, `recipient`, `status`, `provider_ref`, `attempts`, `scheduled_at`, `sent_at`, `error`.

**`wp_bkra_notification_templates`** — `id`, `tenant_id`, `channel`, `event`, `locale`, `subject`, `body`, `is_active`.

**`wp_bkra_intake_fields` / `wp_bkra_booking_intake_answers`** — custom per-service fields and answers.

**`wp_bkra_coupons` / `wp_bkra_booking_coupons`** — discount definitions + usage join.

**`wp_bkra_waitlist_entries`** — `id`, `tenant_id`, `service_id`, `staff_id?`, `customer_id`, `desired_window`, `status`.

**`wp_bkra_affiliates`** — `id`, `wp_user_id`, `code`, `rate`, `status`, `payout_details_enc`.
**`wp_bkra_affiliate_clicks`** — `id`, `affiliate_id`, `ip_hash`, `ua_hash`, `landing`, `clicked_at`.
**`wp_bkra_affiliate_commissions`** — `id`, `affiliate_id`, `booking_id?`, `license_id?`, `amount`, `status` (pending|cleared|clawed_back), `clears_at`.
**`wp_bkra_affiliate_payouts`** — `id`, `affiliate_id`, `amount`, `status`, `processed_at`.

**`wp_bkra_licenses` / `wp_bkra_entitlements`** — license keys, tier, seats, feature flags, expiry, white-label.

**`wp_bkra_audit_log`** — `id`, `tenant_id`, `actor_type`, `actor_id`, `action`, `entity`, `entity_id`, `before_hash`, `after_hash`, `ip_hash`, `created_at` (append-only).

**`wp_bkra_booking_events`** — `id`, `booking_id`, `from_status`, `to_status`, `reason`, `actor`, `created_at` (state-transition history).

**`wp_bkra_report_rollups`** — `id`, `tenant_id`, `date`, `dimension` (staff/service/location/channel), `dimension_id`, `bookings`, `revenue`, `no_shows`, `cancellations` (pre-aggregated for fast reporting).

## 3. Key Relationships (cardinality)

- tenant 1—N {locations, services, staff, customers, bookings, coupons, affiliates}
- staff N—M services (via `staff_services`)
- booking N—1 {service, staff, location, customer}; booking 1—N {payments, notifications, intake answers, events}
- affiliate 1—N {clicks, commissions, payouts}
- coupon N—M bookings

## 4. Indexing Strategy

| Table | Index | Purpose |
|---|---|---|
| bookings | `(tenant_id, staff_id, start_at)` | availability + calendar queries (hot path) |
| bookings | `(tenant_id, customer_id, start_at)` | customer history |
| bookings | `(tenant_id, status, start_at)` | dashboards/today views |
| bookings | unique `(idempotency_key)` | dedupe create |
| booking_holds | `(staff_id, start_at, expires_at)` + TTL cleanup | race prevention |
| payments | `(gateway, gateway_ref)` unique | webhook idempotency |
| notifications | `(status, scheduled_at)` | queue worker pickup |
| staff_schedules | `(staff_id, weekday)` | availability composition |
| affiliate_commissions | `(affiliate_id, status, clears_at)` | payout calc |
| audit_log | `(tenant_id, entity, entity_id, created_at)` | forensics |
| report_rollups | `(tenant_id, date, dimension, dimension_id)` unique | reporting |

All timestamps stored **UTC**; display converted per customer/tenant timezone. Money stored as integer minor units or `DECIMAL(12,2)` with explicit currency (no floats).

## 5. Data Retention Strategy

| Data class | Retention default | Notes |
|---|---|---|
| Active bookings | indefinite while active | — |
| Completed/cancelled bookings | 24 months hot, then archive | configurable per tenant |
| Payment records | 7 years (financial/tax) | jurisdiction-configurable |
| Notification logs | 90 days | delivery troubleshooting |
| Affiliate clicks | 12 months | attribution window + audit |
| Audit log | 24 months min (append-only) | compliance |
| Soft-deleted (`deleted_at`) | purge after 30-day grace | restore window |
| PII on erasure request | anonymize within 30 days | NDPR/GDPR (see security-design) |

## 6. Archiving Strategy

- Cold partitions: bookings/payments older than retention moved to `*_archive` tables (or table partitioning by `start_at` year/month on MySQL 8).
- Archive is read-only, excluded from hot-path indexes/queries; reporting uses rollups, not raw archive.
- Scheduled archive job via Action Scheduler; idempotent, batched (avoids shared-host timeouts).

## 7. Scalability Strategy

- **Read scaling:** object cache (Redis where available, transients otherwise) for availability + entitlements; `report_rollups` removes heavy aggregation from request path.
- **Write hot-path:** narrow booking-create transaction; soft-holds keep lock duration tiny; idempotency keys make retries safe.
- **Partitioning:** time-partition bookings/payments; tenant_id enables future sharding / SaaS extraction.
- **Concurrency:** `SELECT … FOR UPDATE` on hold/slot rows prevents double-booking under load.
- **Shared-host safety:** all batch jobs chunked + resumable; no long-running synchronous loops in web requests.

---
Migrations versioned via `dbDelta` + a custom migration runner with up/down and schema version tracking. Full DDL produced in Stage 1.
