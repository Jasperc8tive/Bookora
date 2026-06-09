# Bookora — Product Requirements Document (PRD)

**Stage:** -1 (Discovery) · **Status:** Build complete · **Version:** 0.1 · **Date:** 2026-06-05

---

## 1. Vision

> Make booking an appointment in Africa — and eventually anywhere — as fast, trusted, and effortless as sending a WhatsApp message, directly from the website businesses already own.

Bookora is a premium WordPress appointment-booking platform engineered for **speed, local payment rails, and the messaging channels customers actually use**. Where incumbents bolt African payments and WhatsApp on as afterthoughts (or not at all), Bookora is built around them from line one, while remaining a credible global Calendly/Amelia alternative.

## 2. Mission

Give every service business — from a Lagos medspa to a London consultant — a booking system that:
- loads fast on a 3G phone,
- takes payment through the rails their customers trust (Paystack, Flutterwave, Stripe),
- confirms and reminds over WhatsApp, SMS, and email,
- and never forces them off their own WordPress site.

## 3. Business Objectives

| # | Objective | Measure |
|---|---|---|
| BO-1 | Establish the default booking plugin for African WordPress SMBs | wp.org active installs, regional share |
| BO-2 | Build a durable freemium → paid revenue engine | Free→paid conversion %, MRR |
| BO-3 | Win an agency/reseller channel (white-label) | # agencies, agency-sourced MRR |
| BO-4 | Be defensibly faster + more local than Bookly/Amelia/LatePoint | Benchmarks, churn vs competitors |
| BO-5 | Reach commercial readiness with audit-clean security & GDPR/NDPR posture | Audit pass, zero criticals at launch |

## 4. Success Metrics (North-Star + supporting)

**North-Star Metric:** *Confirmed paid bookings processed through Bookora per month.* (Aligns customer value, our revenue, and product quality in one number.)

| Layer | Metric | Stage-0 target (illustrative, validated in Stage 0) |
|---|---|---|
| Acquisition | wp.org active installs | 10k in 6 months |
| Activation | % installs that publish a booking form within 24h | ≥ 40% |
| Activation | Time-to-first-booking (TTFB) | < 15 min from install |
| Revenue | Free → paid conversion | 3–5% |
| Revenue | MRR / ARR | Stage-7 targets |
| Engagement | Bookings/active site/month | Cohort growth MoM |
| Performance | Public widget LCP (3G, mid Android) | < 2.5s |
| Reliability | Notification delivery success | ≥ 98% |
| Retention | 6-month logo retention (paid) | ≥ 85% |
| Channel | Agency-sourced revenue share | ≥ 25% by month 12 |

## 5. Core Value Proposition

**For** service businesses on WordPress **who** lose customers to slow, foreign, hard-to-pay booking tools, **Bookora is** a booking platform that **takes local payments, confirms over WhatsApp, and loads instantly**, **unlike** Bookly/Amelia/Calendly which are slow, Western-payment-centric, or pull customers off-site.

## 6. Unique Selling Proposition (USP)

> **The only WordPress booking platform that is Paystack-native, Flutterwave-native, WhatsApp-native, and Elementor-first — fast enough for a 3G connection.**

No single listed competitor holds all five pillars:

| Pillar | Bookora | Bookly | Amelia | LatePoint | SSA | Calendly | QuickCal |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Paystack native | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Flutterwave native | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| WhatsApp native | ✅ | ⚠️ addon | ⚠️ addon | ⚠️ | ❌ | ❌ | ❌ |
| Elementor-first | ✅ | ⚠️ | ✅ | ⚠️ | ⚠️ | n/a | n/a |
| 3G-fast public widget | ✅ | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ |
| Self-hosted (data ownership) | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |

*(⚠️ = partial/paid-addon/heavier. Validated rigorously in Stage 0.)*

## 7. Product Positioning

- **Against Bookly:** "Everything Bookly does, but faster, with local payments and WhatsApp built in — not a maze of paid add-ons."
- **Against Amelia:** "Amelia's polish without the bloat, plus African rails Amelia ignores."
- **Against LatePoint:** "Modern UX and agency white-label, with payment rails LatePoint can't reach."
- **Against Calendly:** "Your brand, your site, your data, your payment gateway — not a rented subdomain."
- **Against SSA/QuickCal:** "Lightweight and fast like them, but a full commerce + notifications platform, not just a scheduler."

**One-liner:** *Bookora — book, pay, and remind, the African way. Globally ready.*

## 8. Scope Boundaries (Stage -1)

**In scope (product):** appointment & service booking, availability/scheduling, staff & locations, payments, notifications (WA/SMS/email), calendar sync, reporting, affiliate, agency/white-label, page-builder integrations.

**Out of scope (now):** full POS/inventory, native mobile apps (PWA only initially), course/LMS, generic CRM. Tracked as post-launch candidates.

**Stage -1 produces documents only — no production code.**

## 9. Constraints & Assumptions

- Must run on commodity shared hosting (PHP 8.1+, MySQL 8 / MariaDB 10.6+, WP 6.x).
- Public pages must avoid heavy JS frameworks (low-bandwidth target).
- Must degrade gracefully when WP-Cron is unreliable (Action Scheduler + optional real cron).
- Free core distributable on wp.org (GPL-compliant; no obfuscated/paid code in the free package).
- Assume non-technical business owners; setup must be wizard-driven.

## 10. Acceptance Criteria for Stage -1

- [x] PRD with vision/mission/objectives/metrics/value prop/USP/positioning
- [x] 6 user types with detailed workflows
- [x] ≥ 100 user stories (delivered: 120)
- [x] 10 use-case specifications
- [x] System architecture (5 views)
- [x] Database design (ERD, relationships, indexing, retention, archiving, scalability)
- [x] API design (endpoints, auth, authz, rate limiting, versioning)
- [x] Security design (threat model, OWASP, data protection, GDPR/NDPR, audit logging)
- [x] Monetization architecture + free/pro/agency/white-label separation
- [x] Seven-part audit + Stage Completion Report
