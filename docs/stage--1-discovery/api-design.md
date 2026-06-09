# Bookora — API Design

**Stage:** -1 · **Status:** Build complete · **Date:** 2026-06-05
Base: WordPress REST API. Namespace: **`bookora/v1`** → `…/wp-json/bookora/v1/…`.

---

## 1. Conventions

- JSON in/out, `utf8mb4`, ISO-8601 UTC timestamps, money as `{amount, currency}` minor units.
- Resource-oriented, plural nouns; standard verbs `GET/POST/PATCH/DELETE`.
- Errors: `{ "code", "message", "data": { "status", "details" } }` (WP `WP_Error` shape).
- Pagination: `?page`, `?per_page` (max 100); `X-WP-Total`, `X-WP-TotalPages` headers.
- Idempotency: mutating create endpoints accept `Idempotency-Key` header.
- All input validated + sanitized at the controller boundary; output escaped.

## 2. Endpoint Catalog (representative)

### Public (customer-facing, unauthenticated or magic-link)
| Method | Path | Purpose |
|---|---|---|
| GET | `/services` | List bookable services (public fields) |
| GET | `/services/{id}/availability?date=&staff=&location=` | Real-time slots |
| POST | `/holds` | Create soft-hold for a slot (returns hold token + TTL) |
| POST | `/bookings` | Create booking (consumes hold; `Idempotency-Key`) |
| POST | `/bookings/{id}/payment-intent` | Init gateway payment |
| GET | `/bookings/{id}?token=` | Fetch booking via magic-link token |
| PATCH | `/bookings/{id}/reschedule?token=` | Reschedule within policy |
| POST | `/bookings/{id}/cancel?token=` | Cancel within policy |
| POST | `/customers/me/data-export` | Request export (GDPR/NDPR) |
| POST | `/customers/me/erasure` | Request erasure |
| POST | `/webhooks/paystack` · `/webhooks/flutterwave` · `/webhooks/stripe` | Signed gateway webhooks |
| POST | `/webhooks/whatsapp` | Delivery/status callbacks |

### Admin / Business Owner (authenticated, capability-gated)
| Method | Path | Purpose |
|---|---|---|
| CRUD | `/admin/services`, `/admin/services/{id}` | Manage services |
| CRUD | `/admin/staff`, `/admin/staff/{id}` | Manage staff |
| CRUD | `/admin/staff/{id}/schedule`, `/time-off` | Availability |
| CRUD | `/admin/locations` | Locations |
| CRUD | `/admin/coupons` | Promotions |
| GET/PATCH | `/admin/bookings`, `/admin/bookings/{id}` | Ops: list, edit, no-show, manual |
| POST | `/admin/bookings/{id}/refund` | Refund via gateway |
| CRUD | `/admin/notification-templates` | Templates |
| GET | `/admin/reports?metric=&range=&dimension=` | Analytics |
| GET | `/admin/health` | Cron/queue/delivery diagnostics |
| GET/POST | `/admin/settings`, `/admin/integrations/*` | Config, OAuth connect |
| POST | `/admin/license/activate` · `GET /admin/license` | Licensing |

### Staff (scoped to self)
| Method | Path | Purpose |
|---|---|---|
| GET | `/staff/me/schedule` | Own calendar |
| PATCH | `/staff/me/availability` | Own hours/time-off |
| POST | `/staff/me/calendar/connect` | OAuth two-way sync |
| PATCH | `/staff/me/bookings/{id}` | Check-in/complete/no-show, notes |

### Agency (multi-tenant)
| Method | Path | Purpose |
|---|---|---|
| GET | `/agency/tenants` | List managed tenants |
| POST | `/agency/tenants` | Provision tenant from template |
| PATCH | `/agency/tenants/{id}/white-label` | Branding |
| POST | `/agency/operators` | Scoped sub-operators |
| GET | `/agency/reports` | Consolidated reporting |
| POST | `/agency/bulk/templates` | Bulk policy/template push |

### Affiliate
| Method | Path | Purpose |
|---|---|---|
| GET | `/affiliate/me/links` | Referral links/codes |
| GET | `/affiliate/me/stats` | Clicks/conversions/commission |
| POST | `/affiliate/me/payouts` | Request payout |

## 3. Authentication Model

- **Admin/Staff/Agency:** WordPress logged-in session + **REST nonce** (`X-WP-Nonce`) for same-origin SPA; **Application Passwords / JWT** for external/headless clients.
- **Customer (public manage):** stateless **magic-link tokens** — short-lived, signed (HMAC), single-purpose, scoped to one booking; optional customer account via WP user.
- **Webhooks:** no session — verified by **provider signature** (HMAC/secret) + timestamp window + idempotency dedupe. Bound to specific tenant/gateway config.
- **Service-to-service (license server):** signed requests with rotating secret.
- Secrets (gateway keys, OAuth tokens) stored **encrypted at rest**; never returned in API responses.

## 4. Authorization Model

- **Capability-based**, mapped onto WordPress capabilities + Bookora-specific caps:
  `bookora_manage_settings`, `bookora_manage_services`, `bookora_manage_staff`, `bookora_manage_bookings`, `bookora_view_reports`, `bookora_manage_agency`, `bookora_view_own_schedule`, `bookora_manage_affiliates`.
- **Tenant scoping guard:** every admin/agency query is filtered by the actor's permitted `tenant_id`(s); cross-tenant access → 403 + audit alert.
- **Entitlement guard:** Pro/Agency endpoints additionally check `LicenseService` feature flags → `403 feature_locked` with upgrade hint (never silently 404).
- **Field-level scoping:** staff see only own bookings/revenue unless granted; customers see only own data.

## 5. Rate Limiting Model

- **Token-bucket** per identity dimension:
  | Surface | Key | Limit (default, tunable) |
  |---|---|---|
  | Availability/holds (public) | IP + tenant | 60 req/min burst 20 |
  | Booking create | IP + session | 10/min, 3 concurrent holds |
  | Webhooks | gateway + signature | high, but signature-gated |
  | Admin API | user | 600/min |
  | Auth/magic-link issue | IP + email | 5/min (anti-enumeration) |
- Storage: object cache (Redis/transients). Exceed → `429` + `Retry-After`. Abuse → temp block + audit.
- Defense-in-depth with bot/spam mitigation on public booking (honeypot, optional CAPTCHA/Turnstile, velocity checks).

## 6. API Versioning Strategy

- **URL-versioned namespace** (`bookora/v1`, future `bookora/v2`) — clear, cache-friendly, WP-idiomatic.
- **Additive changes** (new fields/endpoints) ship within a version; **breaking changes** → new version.
- **Deprecation policy:** min 6-month overlap; `Deprecation` + `Sunset` headers; documented migration notes.
- **Webhook payload versioning** independent of REST version; gateways pinned to verified payload schema.
- Internal services consume a stable contract; the public widget targets the lowest-privilege public subset only.

---
**Documentation:** OpenAPI 3 spec generated in Stage 1; published as developer docs. Contract tests guard every endpoint's auth, authz, validation, and rate-limit behavior.
