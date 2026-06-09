# Bookora — System Architecture

**Stage:** -1 · **Status:** Build complete · **Date:** 2026-06-05
Five views: High-Level, Component, Service, Event, Integration.

---

## 1. High-Level Architecture

```
                         ┌─────────────────────────────────────────────┐
                         │                WordPress Site                │
   Customer (3G phone)   │                                             │
        │                │   ┌───────────────┐   ┌──────────────────┐  │
        ├── public ─────▶│   │ Public Booking │   │  wp-admin (SPA)  │  │
        │   widget (TS)  │   │ Widget (TS)   │   │  React + TS      │  │
        │                │   └───────┬───────┘   └────────┬─────────┘  │
        │                │           │  REST (bookora/v1)  │            │
        │                │   ┌───────▼─────────────────────▼────────┐   │
        │                │   │        Bookora REST Controllers       │   │
        │                │   ├───────────────────────────────────────┤   │
        │                │   │   Service Layer (PHP 8.1, PSR-12)     │   │
        │                │   │   Booking · Availability · Payment ·  │   │
        │                │   │   Notification · License · Calendar · │   │
        │                │   │   Affiliate · Reporting · Tenant      │   │
        │                │   ├───────────────────────────────────────┤   │
        │                │   │   Event Bus  →  Action Scheduler Queue │   │
        │                │   ├───────────────────────────────────────┤   │
        │                │   │   Repository Layer (custom wp_bkra_*)  │   │
        │                │   └───────────────┬───────────────────────┘   │
        │                └───────────────────┼───────────────────────────┘
        │                                    │
        │                         ┌──────────▼──────────┐
        │                         │   MySQL 8 / MariaDB  │
        │                         └─────────────────────┘
        ▼
  External: Paystack · Flutterwave · Stripe · WhatsApp Cloud API · Twilio/SMS ·
            Google Calendar · Microsoft 365 · Zoom/Meet · Bookora License Server
```

**Principles:** layered (controllers → services → repositories), framework-light on the public surface, async-by-default for side effects, driver/adapter pattern for every external dependency, tenant-aware from day one.

## 2. Component Architecture

| Component | Responsibility | Tech |
|---|---|---|
| Public Booking Widget | Render flow, fetch availability, collect details, init payment | Vanilla TS, ~<40KB gz, progressive enhancement, SSR-friendly shell |
| Admin SPA | Configuration, calendar, reports, ops | React 18 + TS, `@wordpress/scripts`, Vite-built, code-split |
| Page-builder integrations | Elementor widgets, Gutenberg blocks, shortcodes | PHP + JS bridges |
| REST Controllers | Validate, authz, marshal to services | WP REST API |
| Service Layer | Business logic, transactions, orchestration | PHP 8.1, DI container |
| Repository Layer | Data access, query building, caching | `wpdb` wrappers + prepared statements |
| Event Bus + Queue | Decouple side effects, retries | Custom dispatcher + Action Scheduler |
| Drivers | Payment / Notification / Calendar adapters | Interface-per-capability |
| License/Entitlement | Resolve tier → capability flags | Local cache + remote license server |
| Admin Health | Cron/queue/delivery diagnostics | PHP + REST + SPA panel |

## 3. Service Architecture

Each service is an interface + implementation, transaction-aware, unit-testable.

- **BookingService** — create/move/cancel; orchestrates holds, payments, notifications; owns booking state machine `pending → confirmed → completed/cancelled/no_show` (+ `abandoned`).
- **AvailabilityEngine** — pure computation of bookable slots from rules; concurrency-safe via row locks / `SELECT … FOR UPDATE` on slot reservation; soft-hold table to prevent races.
- **PaymentGateway** (driver pattern) — `PaystackDriver`, `FlutterwaveDriver`, `StripeDriver`, `PaypalDriver`; idempotent intents; signed webhook verification; refund interface.
- **NotificationDispatcher** (channel drivers) — `WhatsAppDriver` (Cloud API), `SmsDriver`, `EmailDriver`; template rendering; fallback chain; delivery tracking.
- **CalendarSyncService** — Google/Microsoft OAuth, push + incremental pull, busy-block mapping, token refresh.
- **LicenseService** — entitlement resolution, feature flags, white-label config, offline grace period.
- **AffiliateLedger** — attribution, commission accrual/clawback, payouts.
- **ReportingService** — rollup aggregation, exports, role-scoped queries.
- **TenantService** — scoping, provisioning, white-label, sub-operator access control.

**State machine (booking):**
```
            pay/confirm           complete
pending ─────────────────▶ confirmed ─────────▶ completed
   │                          │  │
   │ timeout                  │  └─ no-show ──▶ no_show
   ▼                          ▼
abandoned                 cancelled (+refund)
```

## 4. Event Architecture

Internal event bus emits domain events; subscribers run **async** via Action Scheduler (durable, retryable, survives flaky WP-Cron with optional real-cron trigger).

| Event | Emitted by | Subscribers |
|---|---|---|
| `booking.created` | BookingService | Notifications, CalendarSync, Reporting |
| `booking.confirmed` | BookingService | Notifications (confirm + schedule reminders), CalendarSync, Affiliate |
| `booking.rescheduled` | BookingService | Notifications, CalendarSync |
| `booking.cancelled` | BookingService | Notifications, CalendarSync, Payment(refund), Waitlist |
| `reminder.due` | Scheduler | Notifications |
| `payment.succeeded` / `.failed` / `.refunded` | PaymentGateway | Booking, Reporting, Affiliate |
| `staff.availability.changed` | Scheduling | AvailabilityEngine cache invalidation |
| `license.changed` | LicenseService | Capability cache refresh |

**Delivery guarantees:** at-least-once with idempotency keys; dead-letter surfaced in health dashboard.

## 5. Integration Architecture

| Integration | Mode | Notes |
|---|---|---|
| Paystack | REST + signed webhooks | Cards, bank transfer, USSD; NGN + multi-currency |
| Flutterwave | REST + webhooks | Secondary local rail; mobile money |
| Stripe / PayPal | REST + webhooks | Global tier |
| WhatsApp Cloud API | REST + webhooks | Template messages; optional managed Bookora BSP relay (see R-02) |
| SMS (Twilio/Termii) | REST | Fallback / non-WA markets (Termii = local) |
| Email | wp_mail / SMTP / API (Postmark/SES) | Transactional fallback |
| Google Calendar | OAuth2 + REST + push channels | Two-way sync |
| Microsoft 365 | OAuth2 + Graph API | Two-way sync |
| Zoom / Google Meet | REST | Auto meeting links |
| Elementor / Gutenberg | PHP/JS APIs | First-class page-builder support |
| Bookora License Server | REST | Entitlements, updates, white-label config |

**Integration rules:** every external call behind an interface + circuit breaker + timeout; secrets stored encrypted; all webhooks signature-verified; no third-party call blocks the customer's booking commit (side effects are queued).

---

**Non-functional targets:** public widget LCP < 2.5s on 3G; availability query p95 < 300ms; booking commit p95 < 800ms; horizontal-ready via stateless services + DB as source of truth; cache layer (object cache/transients) for availability and entitlements.
