# Bookora — Master Build Spec (Living Document)

> **Single source of truth.** This document is updated at the end of every completed stage.
> It indexes all stage artifacts, records binding decisions, tracks the stage gate, and holds the roadmap.

---

## 0. Snapshot

| Field | Value |
|---|---|
| Product name | **Bookora** |
| Tagline (working) | *The fastest WordPress booking platform — Africa-first, globally ready.* |
| Category | Premium WordPress appointment-booking platform (plugin + SaaS-grade services) |
| Primary market | **Africa-first (Nigeria lead) → global** |
| Secondary market | Global SMB / Western (Calendly/Amelia displacement) |
| Repo | `c:\Bookora` |
| Current stage | **STAGE 9 — Payments** |
| Stage status | **BUILD COMPLETE → AWAITING APPROVAL** |
| Doc owner | Cross-functional team (Architect, PM, Eng, Security, Growth) |
| Last updated | 2026-06-12 |

---

## 1. Binding Decisions Log

Decisions here are **load-bearing**. Changing one requires a changelog entry and a re-audit of affected stages.

| # | Decision | Rationale | Date | Stage |
|---|---|---|---|---|
| D-001 | Product name is **Bookora** (not "Bookly Pro") | "Bookly" is an existing competitor — direct trademark/SEO collision. | 2026-06-05 | -1 |
| D-002 | **Africa-first** GTM, Nigeria lead, then global | Paystack/Flutterwave/WhatsApp-native mandate signals an underserved, payment-rail-specific market. | 2026-06-05 | -1 |
| D-003 | Distribution = **WordPress plugin** (free core on wp.org) + paid add-on/licensed Pro | Matches Bookly/Amelia/LatePoint model; wp.org free tier is the #1 acquisition channel. | 2026-06-05 | -1 |
| D-004 | Admin UI = **React + TypeScript** SPA mounted in wp-admin; front-end booking = lightweight TS widget (no heavy framework on public pages) | Modern admin DX + fast public LCP for low-bandwidth markets. | 2026-06-05 | -1 |
| D-005 | Backend = **PHP 8.1+**, WordPress REST API namespace `bookora/v1`, custom service layer (not raw WP hooks spaghetti) | Testability, PSR-12, future SaaS extraction. | 2026-06-05 | -1 |
| D-006 | **Custom tables** (not CPT/postmeta) for bookings/appointments | Postmeta does not scale for high-volume time-series booking data. | 2026-06-05 | -1 |
| D-007 | Payments: **Paystack + Flutterwave native**, Stripe/PayPal for global | Differentiator vs all listed competitors in African market. | 2026-06-05 | -1 |
| D-008 | Notifications: **WhatsApp-native** (Cloud API), Email, SMS | WhatsApp is the dominant channel in target market. | 2026-06-05 | -1 |
| D-009 | **Elementor-first** page-builder integration (plus Gutenberg blocks, shortcodes) | Elementor dominates the African/SMB WordPress segment. | 2026-06-05 | -1 |
| D-010 | Licensing/feature-gating via **capability flags resolved from a license entitlement service**, not scattered `if` checks | Clean free/pro separation, white-label readiness. | 2026-06-05 | -1 |
| D-011 | DB table prefix = **`{$wpdb->prefix}bkra_`** → `wp_bkra_*` (supersedes the `af_*` naming in the Stage 1 mandate) | Brand-aligned + collision-safe on shared hosting; `af_` is generic. | 2026-06-08 | 1 |
| D-012 | **18-stage roadmap** (Project Foundation → Production Release) is authoritative, replacing the earlier proposed 1–8. | Detailed engineering mandate supersedes the placeholder roadmap. | 2026-06-08 | 1 |
| D-013 | Admin build = **React 18 + TypeScript + Vite + Tailwind** (Tailwind `bkra-` prefix, scoped to `#bookora-admin-root`) | Modern DX; supersedes `@wordpress/scripts`. | 2026-06-08 | 1 |
| D-014 | **PHP 8.2+ / WP 6.8+**, DDD `app/` layout, lightweight PSR-11 container, service-provider pattern, migration system | Adopt Stage 1 mandate stack; minimal vendor footprint for a commercial plugin. | 2026-06-08 | 1 |
| D-015 | Authorization is **capability-based** (`bookora_*` caps) with a 4-tier role model (Admin/Manager/Staff/Customer); no `manage_options` shortcuts | Least privilege; clean basis for agency/white-label scoping later. | 2026-06-09 | 2 |
| D-016 | Audit log is **append-only + SHA-256 hash-chained**; IP/UA stored HMAC-hashed only | Tamper-evidence + NDPR/GDPR-friendly (no raw PII in logs). | 2026-06-09 | 2 |
| D-017 | All appointment times stored **UTC**; availability rules interpreted in the **WP site timezone**; engine math in epoch seconds | Correct cross-timezone scheduling; DST-safe; single source of truth. | 2026-06-09 | 6 |
| D-018 | Booking concurrency = per-staff **`GET_LOCK`** around check-and-insert + buffer-aware recheck + unique `idempotency_key` | Race-safe double-book prevention on MySQL; degrades to best-effort elsewhere. | 2026-06-09 | 6 |
| D-019 | Public booking via **open `book/*` endpoints** (honeypot + rate limit + server-authoritative pricing/status), distinct from admin-gated booking routes | Booking must be open to visitors; safety via validation not auth. | 2026-06-09 | 7 |
| D-020 | Front-end = **separate Vite entry** (`frontend.js`) mounted by `[bookora_booking]` shortcode; entries loaded as **ES modules** (shared React chunk) | Elementor-compatible; clean admin/front-end separation; no theme-CSS reset. | 2026-06-09 | 7 |
| D-021 | Payments use a **gateway-driver pattern** (`PaymentGateway` interface + `GatewayRegistry`); providers are provider-agnostic to the manager | Paystack/Flutterwave/Stripe today, extensible via `bookora_register_gateways`. | 2026-06-12 | 9 |
| D-022 | Payment confirmation is **webhook-authoritative** with amount+currency match + idempotency; **hosted redirect** checkout (no card data, SAQ-A); secrets masked in settings | Security + PCI scope minimisation; clients can never mark a booking paid. | 2026-06-12 | 9 |

---

## 2. Stage Gate Tracker

Methodology: **Build → Test → Audit → Fix → Retest → Approve**. No stage proceeds without `APPROVED FOR NEXT STAGE`.

| Stage | Name | Status | Approval |
|---|---|---|---|
| **-1** | Discovery, Requirements & Technical Planning | **COMPLETE — Audited** | ✅ APPROVED 2026-06-05 |
| **0** | Market Research & Product Strategy | **COMPLETE — Audited** | ✅ APPROVED 2026-06-08 |
| **1** | Project Foundation | **COMPLETE — Audited** | ✅ APPROVED 2026-06-08 |
| **2** | Authorization + Security Framework | **COMPLETE — Audited** | ✅ APPROVED 2026-06-09 |
| **3** | Services Module | **COMPLETE — Audited** | ✅ APPROVED 2026-06-09 |
| **4** | Staff Management Module | **COMPLETE — Audited** | ✅ APPROVED 2026-06-09 |
| **5** | Customer Management (CRM) | **COMPLETE — Audited** | ✅ APPROVED 2026-06-09 |
| **6** | Booking Engine | **COMPLETE — Audited** | ✅ APPROVED 2026-06-09 |
| **7** | Booking Wizard (front-end) | **COMPLETE — Audited** | ✅ APPROVED 2026-06-12 |
| **8** | Calendar System (admin) | **COMPLETE — Audited** | ✅ APPROVED 2026-06-12 |
| **9** | Payments (Stripe, Paystack, Flutterwave) | **BUILD COMPLETE — Audited** | ⏳ AWAITING APPROVAL |
| 10 | Notifications (Email, SMS, WhatsApp, Push) | Not started | — |
| 11 | Google Calendar (two-way) | Not started | — |
| 12 | Outlook Calendar (MS Graph) | Not started | — |
| 13 | Elementor Integration | Not started | — |
| 14 | Customer Portal | Not started | — |
| 15 | Reporting & Analytics | Not started | — |
| 16 | Advanced Features (waitlist, coupons, memberships, resources) | Not started | — |
| 17 | AI Scheduling | Not started | — |
| 18 | Commercial Hardening (licensing, updater, white-label) | Not started | — |
| Final | Production Release Audit | Not started | — |

> The 18-stage roadmap above is **authoritative** (decision D-012). Each stage follows Build → Test → Audit → Fix → Re-test → Approve and must not proceed without explicit approval.

### Stage 9 Artifact Index

Code in [`app/Payments/`](../app/Payments/) (drivers in [`app/Payments/Gateways/`](../app/Payments/Gateways/)), REST in [`app/API/Controllers/`](../app/API/Controllers/), UI in [`assets/src/admin/components/payments/`](../assets/src/admin/components/payments/). No migration (`payments` table from Stage 1). Stage docs in [`docs/stage-9-payments/`](stage-9-payments/):

| Artifact | File |
|---|---|
| Stage 9 Audit & Plugin Audit Report | [stage-audit.md](stage-9-payments/stage-audit.md) |
| Gateway contract + drivers | [Contracts/PaymentGateway.php](../app/Payments/Contracts/PaymentGateway.php) · [Gateways/](../app/Payments/Gateways/) |
| Payment manager + registry | [PaymentManager.php](../app/Payments/PaymentManager.php) · [GatewayRegistry.php](../app/Payments/GatewayRegistry.php) |
| REST controllers | [PaymentsController.php](../app/API/Controllers/PaymentsController.php) · [PaymentWebhookController.php](../app/API/Controllers/PaymentWebhookController.php) · [PublicPaymentController.php](../app/API/Controllers/PublicPaymentController.php) |
| Admin UI | [PaymentsPage.tsx](../assets/src/admin/components/payments/PaymentsPage.tsx) |

### Stage 8 Artifact Index

Calendar feed in [BookingsController](../app/API/Controllers/BookingsController.php) + [AppointmentRepository::calendar](../app/Appointments/AppointmentRepository.php); UI in [`assets/src/admin/components/calendar/`](../assets/src/admin/components/calendar/). Uses FullCalendar 6. Stage docs in [`docs/stage-8-calendar/`](stage-8-calendar/):

| Artifact | File |
|---|---|
| Stage 8 Audit & Plugin Audit Report | [stage-audit.md](stage-8-calendar/stage-audit.md) |
| Calendar feed + resize | [BookingsController.php](../app/API/Controllers/BookingsController.php) · [BookingEngine.php](../app/Appointments/BookingEngine.php) |
| Admin calendar UI | [CalendarPage.tsx](../assets/src/admin/components/calendar/CalendarPage.tsx) |

### Stage 7 Artifact Index

Public REST in [`app/API/Controllers/PublicBookingController.php`](../app/API/Controllers/PublicBookingController.php), shortcode in [`app/Frontend/`](../app/Frontend/), wizard in [`assets/src/frontend/`](../assets/src/frontend/). Stage docs in [`docs/stage-7-booking-wizard/`](stage-7-booking-wizard/):

| Artifact | File |
|---|---|
| Stage 7 Audit & Plugin Audit Report | [stage-audit.md](stage-7-booking-wizard/stage-audit.md) |
| Public booking controller | [PublicBookingController.php](../app/API/Controllers/PublicBookingController.php) |
| Shortcode + provider | [Shortcode.php](../app/Frontend/Shortcode.php) · [FrontendServiceProvider.php](../app/Frontend/FrontendServiceProvider.php) |
| Module-script loader | [ModuleScript.php](../app/Core/ModuleScript.php) |
| Booking wizard (React) | [BookingWizard.tsx](../assets/src/frontend/BookingWizard.tsx) · [main.tsx](../assets/src/frontend/main.tsx) |

### Stage 6 Artifact Index

Code in [`app/Appointments/`](../app/Appointments/), REST in [`app/API/Controllers/`](../app/API/Controllers/). Migration 0004 adds `wp_bkra_booking_holds`. Engine-only stage (no React UI). Stage docs in [`docs/stage-6-booking-engine/`](stage-6-booking-engine/):

| Artifact | File |
|---|---|
| Stage 6 Audit & Plugin Audit Report | [stage-audit.md](stage-6-booking-engine/stage-audit.md) |
| Clock (timezone) | [Clock.php](../app/Appointments/Clock.php) |
| Repositories | [AppointmentRepository.php](../app/Appointments/AppointmentRepository.php) · [BookingHoldRepository.php](../app/Appointments/BookingHoldRepository.php) |
| Conflict detector | [ConflictDetector.php](../app/Appointments/ConflictDetector.php) |
| Availability engine | [AvailabilityEngine.php](../app/Appointments/AvailabilityEngine.php) |
| Booking engine | [BookingEngine.php](../app/Appointments/BookingEngine.php) |
| REST controllers | [AvailabilityController.php](../app/API/Controllers/AvailabilityController.php) · [BookingsController.php](../app/API/Controllers/BookingsController.php) |

### Stage 5 Artifact Index

Code in [`app/Customers/`](../app/Customers/), REST in [`app/API/Controllers/`](../app/API/Controllers/), UI in [`assets/src/admin/components/customers/`](../assets/src/admin/components/customers/). No migration (tags + polymorphic notes already in Stage-1 schema). Stage docs in [`docs/stage-5-customers/`](stage-5-customers/):

| Artifact | File |
|---|---|
| Stage 5 Audit & Plugin Audit Report | [stage-audit.md](stage-5-customers/stage-audit.md) |
| Repositories | [CustomerRepository.php](../app/Customers/CustomerRepository.php) · [NoteRepository.php](../app/Customers/NoteRepository.php) |
| Manager | [CustomerManager.php](../app/Customers/CustomerManager.php) |
| REST controllers | [CustomersController.php](../app/API/Controllers/CustomersController.php) · [CustomerNotesController.php](../app/API/Controllers/CustomerNotesController.php) |
| Admin UI | [CustomersPage.tsx](../assets/src/admin/components/customers/CustomersPage.tsx) · [CustomerForm.tsx](../assets/src/admin/components/customers/CustomerForm.tsx) |

### Stage 4 Artifact Index

Code in [`app/Staff/`](../app/Staff/), REST in [`app/API/Controllers/`](../app/API/Controllers/), UI in [`assets/src/admin/components/staff/`](../assets/src/admin/components/staff/). Migration 0003 adds `wp_bkra_staff_services` + `staff.skills`. Stage docs in [`docs/stage-4-staff/`](stage-4-staff/):

| Artifact | File |
|---|---|
| Stage 4 Audit & Plugin Audit Report | [stage-audit.md](stage-4-staff/stage-audit.md) |
| Repositories | [StaffRepository.php](../app/Staff/StaffRepository.php) · [AvailabilityRepository.php](../app/Staff/AvailabilityRepository.php) · [StaffServiceRepository.php](../app/Staff/StaffServiceRepository.php) |
| Managers | [StaffManager.php](../app/Staff/StaffManager.php) · [AvailabilityManager.php](../app/Staff/AvailabilityManager.php) |
| REST controllers | [StaffController.php](../app/API/Controllers/StaffController.php) · [StaffAvailabilityController.php](../app/API/Controllers/StaffAvailabilityController.php) |
| Admin UI | [StaffPage.tsx](../assets/src/admin/components/staff/StaffPage.tsx) · [StaffForm.tsx](../assets/src/admin/components/staff/StaffForm.tsx) |

### Stage 3 Artifact Index

Code in [`app/Services/`](../app/Services/), REST in [`app/API/Controllers/`](../app/API/Controllers/), UI in [`assets/src/admin/components/services/`](../assets/src/admin/components/services/). Migration 0002 adds `wp_bkra_service_categories`. Stage docs in [`docs/stage-3-services/`](stage-3-services/):

| Artifact | File |
|---|---|
| Stage 3 Audit & Plugin Audit Report | [stage-audit.md](stage-3-services/stage-audit.md) |
| Service / Category repositories | [ServiceRepository.php](../app/Services/ServiceRepository.php) · [CategoryRepository.php](../app/Services/CategoryRepository.php) |
| Service / Category managers | [ServiceManager.php](../app/Services/ServiceManager.php) · [CategoryManager.php](../app/Services/CategoryManager.php) |
| REST controllers | [ServicesController.php](../app/API/Controllers/ServicesController.php) · [ServiceCategoriesController.php](../app/API/Controllers/ServiceCategoriesController.php) |
| Admin UI | [ServicesPage.tsx](../assets/src/admin/components/services/ServicesPage.tsx) · [ServiceForm.tsx](../assets/src/admin/components/services/ServiceForm.tsx) |

### Stage 2 Artifact Index

Code in [`app/Security/`](../app/Security/) + [`app/Database/Repository/AuditLogRepository.php`](../app/Database/Repository/AuditLogRepository.php). Stage docs in [`docs/stage-2-security/`](stage-2-security/):

| Artifact | File |
|---|---|
| Stage 2 Audit & Plugin Audit Report (incl. permission matrix) | [stage-audit.md](stage-2-security/stage-audit.md) |
| Capabilities + Permission Matrix | [Capabilities.php](../app/Security/Capabilities.php) · [PermissionMatrix.php](../app/Security/PermissionMatrix.php) |
| Roles installer | [Roles.php](../app/Security/Roles.php) |
| Nonce + Guard | [Nonce.php](../app/Security/Nonce.php) · [Guard.php](../app/Security/Guard.php) |
| Rate limiter | [RateLimiter.php](../app/Security/RateLimiter.php) |
| Activity logger (hash-chained) | [ActivityLogger.php](../app/Security/ActivityLogger.php) |

### Stage 1 Artifact Index

Code lives at the repo root (`bookora.php`, `app/`, `assets/`, `tests/`). Stage docs in [`docs/stage-1-foundation/`](stage-1-foundation/):

| Artifact | File |
|---|---|
| Stage 1 Audit & Plugin Audit Report | [stage-audit.md](stage-1-foundation/stage-audit.md) |
| Plugin bootstrap | [bookora.php](../bookora.php) |
| Core (container, lifecycle, settings, logger) | [app/Core/](../app/Core/) |
| Database (migrations, schema, repository) | [app/Database/](../app/Database/) |
| REST API (`bookora/v1`) | [app/API/](../app/API/) |
| Admin shell | [app/Admin/](../app/Admin/) |
| Admin SPA source | [assets/src/admin/](../assets/src/admin/) |

### Stage 0 Artifact Index

All Stage 0 deliverables live in [`docs/stage-0-market-research/`](stage-0-market-research/):

| Artifact | File |
|---|---|
| Competitor Research (6 competitors) | [competitor-research.md](stage-0-market-research/competitor-research.md) |
| Market Gap Analysis | [market-gap-analysis.md](stage-0-market-research/market-gap-analysis.md) |
| Differentiation & Positioning | [differentiation-strategy.md](stage-0-market-research/differentiation-strategy.md) |
| Customer Personas (8) | [personas.md](stage-0-market-research/personas.md) |
| Pricing Strategy | [pricing-strategy.md](stage-0-market-research/pricing-strategy.md) |
| SEO Strategy | [seo-strategy.md](stage-0-market-research/seo-strategy.md) |
| Content Marketing Plan (100/50/25) | [content-marketing-plan.md](stage-0-market-research/content-marketing-plan.md) |
| Affiliate & Partner Strategy | [affiliate-strategy.md](stage-0-market-research/affiliate-strategy.md) |
| Go-To-Market (30/90/6mo/12mo) | [go-to-market.md](stage-0-market-research/go-to-market.md) |
| Stage 0 Audit | [stage-audit.md](stage-0-market-research/stage-audit.md) |

---

## 3. Stage -1 Artifact Index

All Stage -1 deliverables live in [`docs/stage--1-discovery/`](stage--1-discovery/):

| Artifact | File | Covers |
|---|---|---|
| Product Requirements Document | [prd.md](stage--1-discovery/prd.md) | Vision, mission, objectives, metrics, value prop, USP, positioning |
| User Types & Workflows | [user-types-workflows.md](stage--1-discovery/user-types-workflows.md) | Admin, Business Owner, Staff, Customer, Agency, Affiliate workflows |
| User Stories (100+) | [user-stories.md](stage--1-discovery/user-stories.md) | 120 stories across all roles |
| Use Cases | [use-cases.md](stage--1-discovery/use-cases.md) | Booking, reschedule, cancel, payments, notifications, sync, scheduling, reporting, affiliate, agency |
| System Architecture | [system-architecture.md](stage--1-discovery/system-architecture.md) | High-level, component, service, event, integration |
| Database Design | [database-design.md](stage--1-discovery/database-design.md) | ERD, relationships, indexing, retention, archiving, scalability |
| API Design | [api-design.md](stage--1-discovery/api-design.md) | REST endpoints, auth, authz, rate limiting, versioning |
| Security Design | [security-design.md](stage--1-discovery/security-design.md) | Threat model, OWASP map, security arch, data protection, GDPR/NDPR, audit logging |
| Monetization Architecture | [monetization.md](stage--1-discovery/monetization.md) | Tiers, feature gating, free/pro/agency/white-label separation, upgrade paths |
| Stage -1 Audit | [stage-audit.md](stage--1-discovery/stage-audit.md) | 7 audits + Stage Completion Report |

---

## 4. Architecture Summary (authoritative pointers)

- **Stack:** PHP 8.1+ / WordPress 6.x / MySQL 8 (MariaDB 10.6+) / React 18 + TS (admin) / vanilla-TS widget (front).
- **API:** WP REST `wp-json/bookora/v1/*`, versioned, nonce+JWT auth, capability-based authz, token-bucket rate limiting.
- **Data:** Custom tables prefixed `wp_bkra_*`, soft-delete + archive strategy, tenant-scoping column for agency/multi-location.
- **Services:** `BookingService`, `AvailabilityEngine`, `PaymentGateway` (driver pattern), `NotificationDispatcher` (channel drivers), `LicenseService`, `CalendarSyncService`, `AffiliateLedger`.
- **Events:** Internal event bus (`bookora_event` dispatcher) → async queue (Action Scheduler) for notifications, sync, webhooks.
- Full detail in the Stage -1 artifacts above.

---

## 5. Open Questions / Risks Carried Forward

| ID | Item | Owner | Target stage |
|---|---|---|---|
| R-01 | Confirm hosting assumption (shared cPanel vs managed) for the African SMB segment — affects queue/cron strategy. | DevOps | 0 |
| R-02 | WhatsApp Cloud API onboarding friction (Meta Business verification) for SMBs — may need a managed Bookora BSP relay. | Architect/PM | 0 |
| R-03 | Licensing server: self-hosted vs Freemius/Lemon Squeezy/Paddle as merchant-of-record. | Founder/Growth | 0 |
| R-04 | Data residency expectations for NDPR (Nigeria) and GDPR (EU) when offering hosted relays. | Security | 0 |
| R-05 | Free-tier abuse / cron reliability on cheap shared hosting. | DevOps/Eng | 1 |
| R-06 | Live-verify competitor pricing/features + confirm what QuickCal actually is before publishing comparison/pricing pages. | Growth | 0→pre-launch |
| R-07 | "Africa-only" perception capping global TAM — mitigated by dual narrative + global-competitive pillars. | Growth/Founder | ongoing |

---

## 6. Changelog

| Date | Change |
|---|---|
| 2026-06-05 | Document created. Stage -1 built and audited. Decisions D-001…D-010 recorded. Awaiting approval to enter Stage 0. |
| 2026-06-05 | Stage -1 **APPROVED**. Stage 0 (Market Research & Product Strategy) built and audited — 10 artifacts incl. 6 competitor profiles, 8 personas, 100/50/25 content plan, full GTM. Risks R-06, R-07 added. Awaiting approval to enter Stage 1 (first code stage). |
| 2026-06-08 | Stage 0 **APPROVED**. Detailed 18-stage engineering mandate received. Decisions D-011…D-014 recorded (prefix `wp_bkra_`, 18-stage roadmap, React/Vite/Tailwind, PHP 8.2/WP 6.8/DDD). **Stage 1 (Project Foundation) built & audited**: plugin scaffold, DI container, migration system + 12 tables, repository, settings, logger, REST `bookora/v1` + `/system/health`, admin React dashboard. PHPStan/PHPCS/ESLint/Jest/Vite green; PHPUnit WP-integration suite written (run in CI). Awaiting approval to enter Stage 2. Pushed to GitHub (`origin/main`). |
| 2026-06-09 | Stage 1 **APPROVED** + pushed. **Stage 2 (Authorization + Security Framework) built & audited**: 13 capabilities, 4-tier permission matrix, 3 custom roles, namespaced nonces, capability Guard, per-IP REST rate limiter (429), hash-chained append-only activity logger (HMAC IP/UA). Decisions D-015, D-016 recorded. Container hardened for optional deps; `/system/health` + menu moved to `bookora_manage_settings`. PHPStan/PHPCS/Jest green; +17 PHPUnit cases (CI). Awaiting approval to enter Stage 3. Pushed to GitHub. |
| 2026-06-09 | Stage 2 **APPROVED** + pushed. **Stage 3 (Services Module) built & audited**: migration 0002 (`service_categories`); Service/Category repositories with search/filter/paginate; managers with validation+sanitization+slug+audit; REST `ServicesController` (CRUD + bulk) + `ServiceCategoriesController` gated on `bookora_manage_services`; `Router` now gathers controllers via `bookora_rest_controllers` filter (modules self-register); React Services admin (list/search/filters/pagination/bulk + form + media picker). PHPStan/PHPCS/ESLint green; Jest 4/4; +18 PHPUnit cases (CI); Vite build OK. Awaiting approval to enter Stage 4. Pushed to GitHub. |
| 2026-06-09 | Stage 3 **APPROVED** + pushed. **Stage 4 (Staff Management) built & audited**: migration 0003 (`staff_services` join + `staff.skills`); Staff/Availability/StaffService repositories; `StaffManager` (profile + skills + assigned-service sync + audit) and `AvailabilityManager` (validated replace-set of working hours/breaks/time-off/holidays via the availability `type` discriminator); REST `StaffController` + `StaffAvailabilityController` gated on `bookora_manage_staff`; React Staff admin (list + profile/services/skills/weekly-hours/time-off form). PHPStan/PHPCS/ESLint green; Jest 5/5; +15 PHPUnit cases (CI); Vite build OK. Awaiting approval to enter Stage 5. Pushed to GitHub. |
| 2026-06-12 | Stage 8 **APPROVED** + pushed. **Stage 9 (Payments) built & audited**: gateway-driver pattern (`PaymentGateway` + `GatewayRegistry`) with **Paystack / Flutterwave / Stripe** drivers (hosted charge, signature verification, webhook parsing, refunds); `PaymentManager` (full/deposit init, webhook-authoritative confirmation with amount+currency guard + idempotency, manual payments, refund ledger, invoices/receipts, appointment paid/balance reconciliation); admin `PaymentsController` + public `PaymentWebhookController` (signed) + `PublicPaymentController`; React Payments admin (gateway settings + list + refund) and wizard online-pay step (redirect, pay-on-site fallback). Decisions D-021/D-022. PHPStan/PHPCS/ESLint green; Jest 9/9; +9 PHPUnit cases via FakeGateway (CI); build OK (public frontend.js 3.2 KB gz — hosted redirect, no SDK). Live gateway HTTP must be verified in provider test mode before launch. Awaiting approval to enter Stage 10 (Notifications). |
| 2026-06-12 | Stage 7 **APPROVED** + pushed. **Stage 8 (Calendar System) built & audited**: `AppointmentRepository::calendar` (range + filters, joined service/staff/customer + staff colour); `GET /bookings/calendar` FullCalendar feed; `BookingEngine::reschedule` extended for explicit `end` (resize); React `CalendarPage` (month/week/day/agenda, drag=reschedule, resize=duration, staff/status filters, colour-coded) on FullCalendar 6 — isolated to the admin bundle. PHPStan/PHPCS/ESLint green; Jest 8/8; +5 PHPUnit cases (CI); build OK (admin.js 85.9 KB gz, public frontend.js unchanged 3 KB gz). Awaiting approval to enter Stage 9 (Payments). |
| 2026-06-09 | Stage 6 **APPROVED** + pushed. **Stage 7 (Booking Wizard) built & audited**: public `book/*` REST surface (services/staff/availability/hold/appointments) defended by honeypot + rate limiter + server-authoritative pricing; `CustomerManager::resolve_or_create` (dedupe by email/phone); `BookingEngine` `source` tagging; React `BookingWizard` (service→staff→date→time→details→payment→confirmation) with slot holds; `[bookora_booking]` shortcode (Elementor-compatible); 2nd Vite entry + `ModuleScript` (ES-module loading of code-split React); Tailwind `important: true`, no front-end preflight. Decisions D-019/D-020. PHPStan/PHPCS/ESLint green; Jest 7/7; +7 PHPUnit cases (CI); build OK. Known risk: React ~45 KB gz on public page (preact/compat alias is the mitigation). Awaiting approval to enter Stage 8 (Calendar System). |
| 2026-06-09 | Stage 5 **APPROVED** + pushed. **Stage 6 (Booking Engine) built & audited**: migration 0004 (`booking_holds`); `Clock` (UTC↔local, DST-safe); `AppointmentRepository` + `BookingHoldRepository`; `ConflictDetector` (buffer-aware half-open overlap); `AvailabilityEngine` (working hours − breaks − time off/holidays − appts − holds, notice windows, group capacity); `BookingEngine` (create/recurring/group/idempotency/per-staff GET_LOCK, reschedule, cancel, hold); REST `AvailabilityController` + `BookingsController` gated on `bookora_manage_bookings`. Decisions D-017/D-018. PHPStan/PHPCS/ESLint green; Jest 6/6; +18 PHPUnit cases (CI). Engine-only stage (wizard is Stage 7). Awaiting approval to enter Stage 7. |
| 2026-06-09 | Stage 4 **APPROVED** + pushed. **Stage 5 (Customer CRM) built & audited** (no migration — tags + polymorphic notes already in schema): `CustomerRepository` (search/tag-filter/paginate + distinct-tags + booking-history join + stats) and `NoteRepository`; `CustomerManager` (profile validation + duplicate-email guard, tag encode/decode, notes add/list/delete with ownership, merged notes+audit timeline, audit events); `AuditLogRepository::for_entity`; REST `CustomersController` (CRUD + tags + bookings + timeline) + `CustomerNotesController` gated on `bookora_manage_customers`; React Customers admin (list + detail with notes/bookings/timeline). PHPStan/PHPCS/ESLint green; Jest 6/6; +13 PHPUnit cases (CI); Vite build OK. Awaiting approval to enter Stage 6 (Booking Engine). |
