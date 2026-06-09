# Bookora — Affiliate & Partner Strategy

**Stage:** 0 · **Status:** Build complete · **Date:** 2026-06-05
Affiliate structure · commission model · agency referral · partner program.
Implements the Affiliate role/use case ([../stage--1-discovery/use-cases.md](../stage--1-discovery/use-cases.md) UC-9) and ledger ([../stage--1-discovery/database-design.md](../stage--1-discovery/database-design.md)).

---

## 1. Why Affiliate-as-Product-Channel

No listed competitor ships a built-in growth loop. For an Africa-first launch with limited paid-ad budget, **affiliates + agencies are the primary low-CAC acquisition engine**. The program is both a feature (for our customers' businesses) and our own growth channel (recruiting promoters of Bookora itself).

## 2. Affiliate Structure (for promoting Bookora)

| Tier | Who | Rate | Cookie/window | Notes |
|---|---|---|---|---|
| **Standard Affiliate** | Bloggers, creators, WP freelancers | **30% first-year recurring** | 60-day, last-touch + coupon fallback | Self-serve signup |
| **Pro Affiliate** | High-volume / niche authorities | **30–40%**, possibly lifetime recurring on top tiers | 90-day | Approval-based, co-marketing assets |
| **Agency Referral** | Agencies referring *other* agencies/clients | **20–25% recurring** + their own agency discount | 90-day | Stacks with agency rebilling |
| **Influencer / Creator** | YouTube/IG/TikTok in target verticals | Custom + unique coupon | tracked by code | Vertical reach (salon/coach communities) |

**Default decision:** **recurring** commission (not one-time) — aligns affiliates with retention, fits subscription model, beats typical one-time WP-plugin affiliate deals.

## 3. Commission Model Mechanics

- **Attribution:** last-touch within window; cookie + **server-side fallback** + coupon-code attribution (cookie-blocked resilience) via `AffiliateLedger`.
- **Accrual states:** `pending → cleared` after a clearance/refund window (e.g., 30 days); **clawback** on refund/chargeback.
- **Payout:** threshold-gated (e.g., $50/₦ equivalent), via the same rails Bookora trusts (Paystack transfer, PayPal/Wise for global), monthly.
- **Transparency:** affiliate dashboard shows clicks → trials → conversions → pending vs cleared (US-115…US-118).
- **Fraud controls:** self-referral blocks, velocity checks, duplicate-account detection, manual review on anomalies (ties to security audit log).

## 4. Merchant-of-Record Consideration

If R-03 selects **Freemius / Lemon Squeezy / Paddle**, the affiliate program can leverage the provider's built-in affiliate + tax handling (faster to launch, global tax compliance). If self-hosted licensing, the `AffiliateLedger` (already designed) powers it. **Recommendation:** launch on MoR-provider affiliate tooling for v1, retain `AffiliateLedger` for in-product customer-facing affiliate features and future control. Decide with R-03 in Stage 1.

## 5. Agency Referral Program

- Agencies on the **Agency tier** get:
  - A referral link to recruit *other* agencies (recurring commission).
  - Higher rebilling margin on client accounts (they mark up Bookora to clients).
  - Co-branded/white-label sales assets ([content-marketing-plan.md](content-marketing-plan.md) #19 deck).
- Goal: turn agencies into a **multiplier** — each agency brings many client sites (high LTV, low CAC).

## 6. Partner Program (strategic, non-affiliate)

| Partner type | Value exchange |
|---|---|
| **Hosting providers** (esp. African hosts) | Bundle/recommend Bookora → reach SMBs at point of WP setup |
| **Payment partners** (Paystack/Flutterwave ecosystems) | Co-marketing as a featured integration in their app directories |
| **Theme/builder shops** (Elementor ecosystem) | Featured booking solution; bundle deals |
| **Vertical communities** (salon/coach associations) | Group offers, webinars, lead magnets |
| **WP agencies & freelancers** | Implementation partner directory + referral |

## 7. Recruitment Plan

1. **Content creators** in target verticals (salon/coach/consultant YouTubers) — unique coupons.
2. **WP-niche bloggers/affiliates** — comparison/review content (overlaps SEO).
3. **Existing happy customers** — in-product referral nudge after value moment (Nth booking).
4. **Agencies** — direct outreach + agency landing page.
5. **Payment/hosting ecosystems** — partnership BD.

## 8. KPIs

| Metric | Target signal |
|---|---|
| Affiliate-sourced signups % | ≥ 20% of new free installs by month 6 |
| Agency-sourced MRR % | ≥ 25% by month 12 (BO-3) |
| Affiliate activation (≥1 referral) | Program health |
| Commission ROI vs CAC | Cheaper than paid ads |
| Refund/clawback rate | Fraud/quality signal |

→ Sequenced into launch in [go-to-market.md](go-to-market.md).
