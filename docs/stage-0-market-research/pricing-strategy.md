# Bookora — Pricing Strategy

**Stage:** 0 · **Status:** Build complete · **Date:** 2026-06-05
Builds on monetization architecture ([../stage--1-discovery/monetization.md](../stage--1-discovery/monetization.md)) with market-validated reasoning.
> ⚠️ Final numbers pending live competitor verification (R-06) + willingness-to-pay testing. Ranges below are strategy-grade.

---

## 1. Pricing Principles

1. **Bundled tiers, not add-ons** — directly attack Bookly/LatePoint add-on fatigue. Each tier is complete for its segment.
2. **Generous free tier** — wp.org acquisition flywheel; free Bookora must be genuinely useful (single business, core booking, Paystack/Flutterwave, WA confirmations, email).
3. **Value-based, ROI-anchored** — price against *no-show reduction + revenue captured*, not against the cheapest plugin.
4. **PPP/regional pricing** — Africa-first localized pricing; global pricing for Western tier (protects both volume and margin).
5. **Annual default + lifetime/early-adopter offers** — cash flow + emerging-market preference for one-time.
6. **Simple, legible ladder** — a non-technical owner understands which tier they need in 30 seconds.

## 2. Tier Ladder (strategy-grade pricing)

| Tier | Who | Monthly (global) | Annual (global) | Africa/PPP note |
|---|---|---|---|---|
| **Free** | Solo, trial | $0 | $0 | Full local rails + WA confirmations |
| **Starter** | Solo pro / small shop | ~$9/mo | ~$84/yr | Localized ~₦/PPP equivalent, lower |
| **Business** | Established SMB, multi-staff | ~$29/mo | ~$276/yr | Localized equivalent |
| **Agency** | Agencies/resellers | ~$119/mo | ~$1,140/yr | Many sites/tenants |
| **Enterprise/White-Label** | Resellers, own brand | custom | custom | + white-label, SLA |

**Anchoring vs market:** Below Calendly's per-seat cost at scale; competitive with Amelia/SSA on annual; differentiated by *included* local rails + WhatsApp (which others can't match at any price).

## 3. Feature Matrix (commercial view)

(Authoritative capability matrix lives in [../stage--1-discovery/monetization.md](../stage--1-discovery/monetization.md) §3.) Pricing-relevant gates:

| Value driver | Free | Starter | Business | Agency | WL |
|---|:--:|:--:|:--:|:--:|:--:|
| Local rails (Paystack/Flutterwave) | ✅ | ✅ | ✅ | ✅ | ✅ |
| WhatsApp confirmations | ✅ | ✅ | ✅ | ✅ | ✅ |
| WhatsApp/SMS reminders + templates | — | ✅ | ✅ | ✅ | ✅ |
| Deposits / Stripe / coupons | — | ✅ | ✅ | ✅ | ✅ |
| Multi-location / recurring / intake | — | — | ✅ | ✅ | ✅ |
| Advanced reports / audit log | — | — | ✅ | ✅ | ✅ |
| Multi-tenant / rebilling | — | — | — | ✅ | ✅ |
| Full white-label | — | — | — | partial | ✅ |

## 4. Upgrade Flow (conversion mechanics)

```
Install Free (wp.org) ──activation──▶ Setup wizard ──first booking──▶ value realized
        │                                                   │
   hit limit (4th staff,                          contextual upgrade prompt
   want deposits/reminders)                       (exact benefit, 1-click)
        ▼                                                   ▼
     Starter ──multi-location/recurring/reports──▶ Business ──manage many clients──▶ Agency ──own brand──▶ White-Label
```
- **Triggers:** limit hit, locked-feature click, milestone (Nth booking / first no-show).
- **Mechanic:** in-product, non-nagging, value-framed; license key unlocks **without reinstall** (entitlement refresh, see monetization §5).
- **Win-back:** lapsed license → read-only retention + targeted reactivation offer.

## 5. Revenue Model

- **Primary:** recurring subscriptions (MRR/ARR), annual-default.
- **Secondary:** Agency seats (high ACV), lifetime/early-adopter (launch cash), white-label/enterprise (custom).
- **Margin upsells (later):** managed WhatsApp BSP relay, hosted SMS/email credits — usage-based, margin-friendly, optional (R-02/R-03 dependent).
- **Channel revenue:** affiliate-driven signups + agency rebilling multiply reach without linear CAC.

## 6. LTV Strategy

| Lever | Effect on LTV |
|---|---|
| No-show reduction (deposits + reminders) | Customer sees direct ROI → low churn |
| Multi-channel comms stickiness | Switching cost rises (templates, history) |
| Agency standardization | High retention, expansion across clients |
| Annual billing | Reduces monthly-churn surface |
| Vertical fit (medspa/clinic/lawyer) | Higher satisfaction → retention + referrals |
| Data ownership | No "rented subdomain" churn risk like Calendly |

**Target unit economics (to validate):** LTV:CAC ≥ 3:1; payback < 12 months; gross margin high (software + thin usage passthrough). Detailed projections in Stage 7.

## 7. Open Pricing Decisions (carried)

- **R-03** Merchant-of-record: Freemius / Lemon Squeezy / Paddle (handles global tax/VAT/MoR, EDD-style WP licensing) vs self-hosted. Strongly lean **MoR provider** for tax simplicity in a global+African sale. Decide in Stage 1.
- **R-06** Verify live competitor pricing before publishing comparison/pricing pages.
- WTP testing (landing-page price tests, interviews) recommended pre-launch.

→ Feeds [go-to-market.md](go-to-market.md) and Stage 7 commercialization.
