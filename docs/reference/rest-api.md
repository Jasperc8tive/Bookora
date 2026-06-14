# Bookora — REST API Reference

All routes live under the namespace **`bookora/v1`** (e.g. `/wp-json/bookora/v1/system/health`).

## Conventions

* **Auth (admin):** cookie authentication + the `X-WP-Nonce` header (WordPress core
  verifies it). Each route also checks a Bookora capability.
* **Auth (public):** the booking (`/book/*`) and portal (`/portal/*`, `/me/*`) routes
  are public or token-scoped — the customer portal sends a signed magic-link token in
  the `X-Bookora-Portal-Token` header; every action re-checks ownership.
* **Success envelope:** `{ "success": true, "data": … }`.
* **Error envelope:** a `WP_Error` rendered by core → `{ "code", "message", "data": { "status" } }`.
* **Capabilities** referenced below are the `bookora_*` capabilities (see the developer guide).

## Admin endpoints

| Method | Route | Capability | Purpose |
|---|---|---|---|
| GET | `/system/health` | `manage_settings` | Plugin/DB/PHP/WP versions, migration + table status |
| GET/POST | `/services` | `manage_services` | List / create services |
| POST | `/services/bulk` | `manage_services` | Bulk operations |
| GET/POST | `/service-categories` | `manage_services` | Service categories |
| GET/POST | `/staff` | `manage_staff` | List / create staff |
| GET | `/availability` | `manage_bookings` | Admin availability lookup |
| GET/POST | `/customers` | `manage_customers` | List / create customers |
| GET | `/customers/tags` | `manage_customers` | Customer tags |
| GET/POST | `/bookings` | `manage_bookings` | List / create bookings |
| GET | `/bookings/calendar` | `manage_bookings` | Calendar feed |
| POST | `/bookings/hold` | `manage_bookings` | Create a booking hold |
| GET/POST | `/payments` | `manage_payments` | Payments list |
| GET/POST | `/payments/settings` | `manage_payments` | Gateway settings (secrets masked) |
| GET/POST | `/notifications/settings` | `manage_settings` | Channels, reminders, templates |
| GET | `/notifications/log` | `manage_settings` | Delivery log |
| POST | `/notifications/test` | `manage_settings` | Send a test message |
| GET | `/reports/overview` | `view_reports` | KPIs, revenue, conversion |
| GET | `/reports/utilization` | `view_reports` | Per-staff utilisation |
| GET | `/reports/export` | `view_reports` | CSV export |
| GET/POST/DELETE | `/coupons`, `/gift-cards`, `/memberships`, `/resources`, `/waitlist` | `manage_bookings` | Advanced-feature CRUD |
| GET | `/scheduling/suggestions` | `manage_bookings` | Ranked smart slots |
| GET | `/scheduling/auto-assign` | `manage_bookings` | Least-loaded staff for a slot |
| GET | `/scheduling/forecast` | `manage_bookings` | Demand forecast |
| GET | `/scheduling/workload` | `manage_bookings` | Per-staff workload |
| GET/POST | `/integrations/google`, `/integrations/microsoft` | `manage_settings` | Calendar connection status / config |
| GET | `/integrations/{provider}/connect` | `manage_settings` | Begin OAuth |
| GET | `/integrations/{provider}/callback` | (state-token) | OAuth callback |
| GET | `/commercial/license` | `manage_settings` | License status |
| POST | `/commercial/license/activate` · `/deactivate` | `manage_settings` | Manage license |
| GET | `/commercial/features` | `manage_settings` | Tier + feature flags |
| GET/POST | `/commercial/branding` | `manage_settings` | White-label settings (agency) |
| GET/POST | `/commercial/telemetry` | `manage_settings` | Opt-in toggle + payload preview |
| GET | `/commercial/export` | `manage_settings` | Full data export document |
| POST | `/commercial/import` | `manage_settings` | Import (requires `confirm`) |
| GET/POST | `/commercial/backups` | `manage_settings` | List / create backups |
| POST | `/commercial/backups/restore` | `manage_settings` | Restore (requires `confirm`) |
| POST | `/commercial/backups/delete` | `manage_settings` | Delete a backup |

## Public / front-end endpoints

| Method | Route | Auth | Purpose |
|---|---|---|---|
| GET | `/book/services` | public | Bookable services |
| GET | `/book/availability` | public | Open slots for a service/date |
| POST | `/book/hold` | public | Hold a slot during checkout |
| POST | `/book/appointments` | public | Create a booking |
| GET | `/book/pay/gateways` | public | Enabled payment gateways |
| POST | `/book/pay/initialize` | public | Start hosted checkout |
| GET | `/book/pay/status` | public | Poll payment status |
| POST | `/coupons/validate` | public | Validate a coupon code |
| GET | `/gift-cards/balance` | public | Gift-card balance lookup |
| POST | `/waitlist/join` | public | Join a waitlist |
| POST | `/memberships/enroll` | public | Enrol in a membership |

## Customer-portal endpoints (token-scoped)

| Method | Route | Auth | Purpose |
|---|---|---|---|
| POST | `/portal/request-link` | public (no enumeration) | Email a magic link |
| GET | `/portal/me` | portal token | Profile |
| GET | `/portal/bookings` | portal token | Upcoming/past bookings |
| GET/POST/DELETE | `/me/events`, `/me/events/{id}` | portal token | View / reschedule / cancel own bookings (window-enforced) |

> Route inventory is generated from the controllers in `app/API/Controllers/` (plus
> the portal and integration controllers). Capabilities and request/response shapes
> are defined in each controller; this table is the authoritative index of paths.
