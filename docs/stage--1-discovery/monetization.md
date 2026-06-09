# Bookora — Monetization Architecture & Free/Pro Separation

**Stage:** -1 · **Status:** Build complete · **Date:** 2026-06-05
Tiers · feature matrix · gating strategy · package separation · upgrade paths.
*Prices are placeholders — validated in Stage 0 pricing research.*

---

## 1. Packaging Model

Four distributable packages, five commercial tiers:

| Package | Distribution | Tiers served |
|---|---|---|
| **Core** | Free on wp.org (GPL) | Free |
| **Premium** (Pro) | Licensed add-on / Pro build | Starter, Business |
| **Agency** | Licensed | Agency |
| **White Label** | Licensed (top tier) | Enterprise/White-Label |

> Free Core is fully functional for a single small business — it is the #1 acquisition channel (D-003), not crippleware.

## 2. Tiers & Pricing (placeholder)

| Tier | Target | Indicative price | Seats/sites |
|---|---|---|---|
| **Free** | Solo / trying it out | $0 | 1 site, 1 staff |
| **Starter** | Solo pro / small shop | ~$7–12/mo or annual | 1 site, up to 3 staff |
| **Business** | Established SMB / multi-staff | ~$25–39/mo | 1 site, unlimited staff, multi-location |
| **Agency** | Agencies/resellers | ~$99–149/mo | many sites/tenants |
| **Enterprise/White-Label** | Resellers wanting own brand | custom | unlimited + white-label |

## 3. Feature Matrix

| Capability | Free | Starter | Business | Agency | White-Label |
|---|:--:|:--:|:--:|:--:|:--:|
| Unlimited bookings | ✅ | ✅ | ✅ | ✅ | ✅ |
| Services & categories | ✅ | ✅ | ✅ | ✅ | ✅ |
| Single staff | ✅ | ✅(3) | ✅(∞) | ✅ | ✅ |
| Paystack + Flutterwave | ✅ | ✅ | ✅ | ✅ | ✅ |
| Stripe / PayPal | — | ✅ | ✅ | ✅ | ✅ |
| Deposits & partial pay | — | ✅ | ✅ | ✅ | ✅ |
| WhatsApp confirmations | ✅(basic) | ✅ | ✅ | ✅ | ✅ |
| WhatsApp + SMS reminders & templates | — | ✅ | ✅ | ✅ | ✅ |
| Email notifications | ✅ | ✅ | ✅ | ✅ | ✅ |
| Elementor / Gutenberg / shortcode | ✅ | ✅ | ✅ | ✅ | ✅ |
| Coupons & taxes | — | ✅ | ✅ | ✅ | ✅ |
| Multi-location | — | — | ✅ | ✅ | ✅ |
| Group/class bookings | — | ✅ | ✅ | ✅ | ✅ |
| Recurring appointments | — | — | ✅ | ✅ | ✅ |
| Google/Outlook 2-way sync | — | ✅ | ✅ | ✅ | ✅ |
| Zoom/Meet auto-links | — | ✅ | ✅ | ✅ | ✅ |
| Custom intake forms | — | — | ✅ | ✅ | ✅ |
| Waitlist | — | ✅ | ✅ | ✅ | ✅ |
| Reporting & analytics | basic | ✅ | ✅(advanced) | ✅ | ✅ |
| Customer portal & history | ✅(basic) | ✅ | ✅ | ✅ | ✅ |
| Audit log viewer | — | — | ✅ | ✅ | ✅ |
| Affiliate program (as affiliate) | — | ✅ | ✅ | ✅ | ✅ |
| Multi-tenant console | — | — | — | ✅ | ✅ |
| Bulk template/policy push | — | — | — | ✅ | ✅ |
| Client rebilling | — | — | — | ✅ | ✅ |
| Full white-label (brand/logo/remove "Powered by") | — | — | — | partial | ✅ |
| Priority support | — | — | ✅ | ✅ | ✅ |

## 4. Feature Gating Strategy (D-010)

- **Entitlements, not scattered `if`s.** `LicenseService` resolves a license → an **entitlement set** (feature flags + limits + white-label config), cached locally with an offline grace period.
- **Single gate API:** `Entitlements::can('feature.key')` and `Entitlements::limit('staff.max')` used uniformly across PHP services, REST controllers, and surfaced to the React admin (which hides/locks UI but **server still enforces** — UI gating is cosmetic only).
- **Server-authoritative:** every Pro/Agency REST endpoint checks entitlement → `403 feature_locked` with an upgrade hint (never silently hide → avoids "is it broken?" confusion).
- **Graceful downgrade:** if a license lapses, data is retained read-only; Pro features lock but nothing is destroyed.
- **Clean package separation:** free Core ships no obfuscated/paid code (wp.org GPL compliance); Pro/Agency/White-Label features live in licensed packages that register additional capabilities and drivers via the same interfaces.
- **White-label config** (brand name, logo, colors, "Powered by" toggle, support URLs) delivered as part of the entitlement payload, applied centrally to admin SPA + notifications + widget.

## 5. Upgrade Paths

```
Free ──(need Stripe/deposits/reminders)──▶ Starter
Starter ──(multi-location/recurring/intake/advanced reports)──▶ Business
Business ──(manage many clients)──▶ Agency
Agency ──(own brand, resell)──▶ White-Label/Enterprise
```

**In-product upgrade triggers (contextual, non-nagging):**
- Hitting a limit (e.g., adding 4th staff on Starter) → inline upgrade prompt with the exact benefit.
- Clicking a locked feature → modal explaining value + 1-click upgrade.
- Milestone nudges (e.g., after Nth booking, after first no-show) → relevant Pro suggestion.
- Annual discount + lifetime/early-adopter offers for African market price-sensitivity.

**Upgrade architecture:** entering/upgrading a license key → `LicenseService` revalidates with license server → entitlement cache refresh (`license.changed` event) → features unlock **without reinstall**; downgrade is symmetric and non-destructive.

## 6. Revenue & Retention Notes (handed to Stage 0)

- **Revenue model:** recurring subscriptions (primary) + annual/lifetime options; agency seats; potential usage add-ons (managed WhatsApp BSP relay, hosted SMS credits) as margin-friendly upsells.
- **LTV levers:** multi-channel notifications + deposits reduce no-shows → customers see ROI → retention.
- **Local pricing:** purchasing-power-adjusted pricing/regional offers for African market; global pricing for Western tier.
- **Affiliate + agency channel:** see [user-types-workflows.md](user-types-workflows.md) §5–6; commission model designed in Stage 0.
- Open: merchant-of-record decision (Freemius/Lemon Squeezy/Paddle vs self-hosted) — **R-03**, resolved in Stage 0.

---
Detailed pricing validation, willingness-to-pay, competitor price benchmarking, and revenue projections are **Stage 0** deliverables.
