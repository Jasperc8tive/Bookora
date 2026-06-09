# Bookora — Competitor Research

**Stage:** 0 · **Status:** Build complete · **Date:** 2026-06-05
> ⚠️ Pricing/feature data reflects knowledge as of **early 2026** and must be **live-verified** before pricing lock (tracked as risk R-06). Directional analysis is stable regardless of exact prices.

---

## 1. Competitor Profiles

### 1.1 Bookly (WordPress)
- **Model:** Freemium. *Bookly Lite* free on wp.org; *Bookly PRO* sold on CodeCanyon (~$89 one-time, regular-license) + a large catalog of **paid add-ons** (Payments, Staff, Recurring, Group, Coupons, etc.).
- **Strengths:** Huge install base, mature, lots of add-ons, broad gateway support, established brand.
- **Weaknesses:** Monetization is **add-on hell** — basic needs require stacking paid extensions; dated UX; heavier front-end; WhatsApp/local-African rails weak or add-on-gated; support reputation mixed.
- **Tech/UX:** PHP/jQuery era admin, custom tables (good), front-end form not the fastest.
- **Positioning:** "The complete booking plugin" via modular paid add-ons.

### 1.2 Amelia (WordPress)
- **Model:** Freemium (Amelia Lite free). Paid: roughly **Basic / Pro / Developer**, annual + lifetime options (~$59 / $89 / $199 tiers historically).
- **Strengths:** **Best-in-class UX/visual polish** among WP booking plugins, events + appointments, good calendar sync, Elementor/Gutenberg/Divi support, strong marketing/SEO.
- **Weaknesses:** Can feel heavy; some advanced logic gated to top tier; African payment rails (Paystack/Flutterwave) **not native**; WhatsApp via workarounds; price resistance in emerging markets.
- **Tech/UX:** Vue-based admin, modern, polished; custom tables.
- **Positioning:** "Beautiful, enterprise-grade booking & events for WordPress."

### 1.3 LatePoint (WordPress)
- **Model:** One-time license (~$49 single-site historically) + paid add-ons; lifetime feel.
- **Strengths:** Modern booking flow UX, attractive front-end, agency-friendly pricing, growing fast, good drag-drop calendar.
- **Weaknesses:** Add-on-gated features; smaller ecosystem than Bookly/Amelia; payments skew Western (Stripe/PayPal/WooCommerce); no native Paystack/Flutterwave/WhatsApp; support scaling pains.
- **Tech/UX:** Clean modern front-end, decent admin.
- **Positioning:** "Modern, affordable booking with a great UX."

### 1.4 Simply Schedule Appointments (SSA) (WordPress)
- **Model:** Freemium. Free core; **Plus / Professional / Business** paid (~$99 / $199 / $299+ per year historically).
- **Strengths:** **Lightweight, fast, reliable**, excellent onboarding/support reputation, Gutenberg-first, great for simple Calendly-style WP scheduling, strong docs.
- **Weaknesses:** More **scheduler than commerce platform** — lighter on payments/deposits, multi-staff/agency, marketing features; no African rails/WhatsApp focus.
- **Tech/UX:** React admin, clean, fast.
- **Positioning:** "The easiest, most reliable WordPress appointment scheduler."

### 1.5 Calendly (SaaS, not WordPress-native)
- **Model:** SaaS subscription per seat. **Free / Standard (~$10–12/seat/mo) / Teams (~$16/seat/mo) / Enterprise (custom)**.
- **Strengths:** Category-defining brand, frictionless 1:1 scheduling, integrations galore, polished, network effects.
- **Weaknesses:** **Not self-hosted** (rented subdomain, no data ownership), **not a payments/commerce platform** for service businesses, no WordPress-native ownership, per-seat cost scales painfully, no African rails, generic (not vertical) UX.
- **Tech/UX:** Best-in-class SaaS UX.
- **Positioning:** "Easy scheduling ahead — individual & team meeting scheduling."

### 1.6 QuickCal
- **Model:** ⚠️ **Low-confidence / verify.** Positioned as a fast, modern, often Calendly-style scheduling tool (possibly SaaS or lightweight plugin). Limited public footprint vs the others.
- **Likely strengths:** Speed, simplicity, modern UI.
- **Likely weaknesses:** Shallow feature depth, small ecosystem, no African rails/WhatsApp, unproven.
- **Action:** **R-06 priority** — confirm what QuickCal actually is (WP plugin vs SaaS), pricing, and traction via live research before citing it in marketing comparisons.

---

## 2. Feature Comparison Matrix

| Capability | Bookora (planned) | Bookly | Amelia | LatePoint | SSA | Calendly | QuickCal* |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Self-hosted / data ownership | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ? |
| WordPress-native | ✅ | ✅ | ✅ | ✅ | ✅ | ❌(embed) | ? |
| Free tier | ✅ | ✅(Lite) | ✅(Lite) | ❌ | ✅ | ✅ | ? |
| **Paystack native** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Flutterwave native** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **WhatsApp native** | ✅ | ⚠️addon | ⚠️ | ⚠️ | ❌ | ❌ | ❌ |
| Stripe/PayPal | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ? |
| Deposits/partial | ✅ | ⚠️addon | ✅ | ⚠️addon | ⚠️ | ❌ | ? |
| Multi-staff | ✅ | ⚠️addon | ✅ | ✅ | ⚠️ | ✅ | ? |
| Group/events | ✅ | ⚠️addon | ✅ | ⚠️ | ⚠️ | ⚠️ | ? |
| 2-way calendar sync | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ? |
| Elementor-first | ✅ | ⚠️ | ✅ | ⚠️ | ⚠️ | n/a | ? |
| Agency/white-label | ✅ | ⚠️ | ⚠️ | ✅(better) | ⚠️ | ❌ | ? |
| Built-in affiliate (as product channel) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ? |
| 3G-fast public widget | ✅ | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ? |

`*` QuickCal column intentionally `?` pending verification (R-06).

## 3. Pricing Snapshot (verify before quoting)

| Product | Entry paid | Mid | Top | Model |
|---|---|---|---|---|
| Bookly | ~$89 one-time (PRO) + add-ons | add-ons stack | — | One-time + paid add-ons |
| Amelia | ~$59/yr | ~$89/yr | ~$199 (lifetime/dev) | Annual + lifetime |
| LatePoint | ~$49 one-time | + add-ons | agency | One-time + add-ons |
| SSA | ~$99/yr | ~$199/yr | ~$299+/yr | Annual SaaS-style |
| Calendly | Free | ~$10–12/seat/mo | ~$16/seat/mo + Enterprise | Per-seat SaaS |
| QuickCal | ? | ? | ? | ? |

## 4. Review-Theme Synthesis (what users praise / complain about)

| Theme | Praised in | Complained about in |
|---|---|---|
| Ease/onboarding | SSA, Calendly | Bookly (complexity) |
| UX/design | Amelia, LatePoint, Calendly | Bookly (dated) |
| "Nickel-and-dimed by add-ons" | — | **Bookly, LatePoint** |
| Support quality | SSA | Bookly, Amelia (mixed at scale) |
| Performance | SSA, Calendly | Bookly/Amelia (heavier) |
| Local payments (Africa) | **none** | **all** (gap) |
| WhatsApp comms | **none native** | requested everywhere |
| Data ownership | WP plugins | Calendly (rented subdomain) |

## 5. Strategic Read

- **No incumbent owns African payment rails or WhatsApp natively** → Bookora's clearest, defensible wedge.
- **"Add-on fatigue"** (Bookly/LatePoint) is a recurring pain → Bookora should ship coherent **bundled tiers**, not pay-per-feature add-ons.
- **Amelia sets the UX bar**; **SSA sets the speed/support bar** → Bookora must match both, not just undercut on price.
- **Calendly proves demand** but its non-ownership + per-seat cost is a wedge for a self-hosted, flat-priced alternative.
- Carried risk: **R-06** verify QuickCal + live pricing/features before publishing comparison pages.

→ Feeds [market-gap-analysis.md](market-gap-analysis.md) and [differentiation-strategy.md](differentiation-strategy.md).
