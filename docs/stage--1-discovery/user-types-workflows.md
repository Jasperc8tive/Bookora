# Bookora — User Types & Detailed Workflows

**Stage:** -1 · **Status:** Build complete · **Date:** 2026-06-05

Six first-class roles. Each has a capability set (mapped to WordPress capabilities in Stage 1) and a primary workflow.

---

## Role matrix

| Role | Scope | Key capabilities | Tier where unlocked |
|---|---|---|---|
| Administrator | Whole WP site | Install, configure, manage everything, manage licenses | Free |
| Business Owner | One business/tenant | Services, staff, schedules, payments, reports | Free → Pro |
| Staff Member | Own calendar | View/manage own appointments, set availability | Free (limited) → Business |
| Customer | Self | Book, pay, reschedule, cancel, view history | Free |
| Agency | Multiple client sites/tenants | Manage many businesses, white-label, billing | Agency |
| Affiliate | Self + referrals | Referral links, track conversions, payouts | Pro/Agency |

---

## 1. Administrator

**Who:** WordPress site admin / technical owner. Installs and owns the platform.

**Workflow — First-run setup:**
1. Installs Bookora from wp.org → activation triggers **Setup Wizard**.
2. Wizard: business type → timezone/currency → first service → first staff → payment gateway connect (Paystack/Flutterwave/Stripe) → notification channel connect (WhatsApp/SMS/email) → publish first booking page (Elementor/Gutenberg/shortcode).
3. Enters Pro license key (if purchased) → `LicenseService` resolves entitlements → Pro features unlock.
4. Sets global policies: cancellation window, buffers, deposits, GDPR/NDPR consent text, audit-log retention.
5. Manages roles & capabilities; invites Business Owners/Staff.

**Ongoing:** updates, backups guidance, health dashboard (cron status, queue depth, delivery rates), security/audit log review, data export/erasure requests.

---

## 2. Business Owner

**Who:** Owns/runs the service business (medspa, salon, clinic, coach). May == Administrator on single-site, or a delegated role.

**Workflow — Configure & operate:**
1. Defines **services** (name, duration, price, deposit, buffer, capacity, category, assigned staff, location).
2. Defines **staff** and assigns services + working hours + days off + holidays.
3. Sets **locations** (physical/virtual; virtual → auto Zoom/Google Meet link).
4. Configures **booking rules**: lead time, min/max notice, reschedule/cancel policy, group bookings, recurring.
5. Sets **payments**: full / deposit / pay-on-site; taxes; coupons; gateways.
6. Configures **notifications**: templates per channel/event, reminder cadence.
7. Publishes booking flow on pages; embeds widgets.
8. **Daily ops:** dashboard of today's appointments, drag-to-reschedule calendar, walk-in/manual booking, mark no-show, take payment, refund.
9. **Reporting:** revenue, occupancy, staff utilization, no-show rate, channel performance; export.

---

## 3. Staff Member

**Who:** Provider delivering the service (stylist, doctor, trainer).

**Workflow:**
1. Receives invite → sets own profile, photo, bio, services they offer.
2. Manages **own availability**: working hours, breaks, time-off requests, personal calendar sync (Google/Outlook two-way) so external events block slots.
3. Views **own schedule** (day/week), gets WA/email notifications of new/changed bookings.
4. Marks attendance (checked-in, completed, no-show); adds private appointment notes.
5. (Business tier) sees own performance metrics; cannot see other staff's revenue unless granted.

---

## 4. Customer

**Who:** End client booking the service. Non-technical, often on mobile/3G.

**Workflow — Book:**
1. Lands on business's WordPress page → fast-loading widget.
2. Selects service → (optional) staff/"any available" → location → date → time slot (real-time availability).
3. Enters details (name, phone, email); returning customers recognized by phone/email or magic-link login.
4. Pays (full/deposit) via Paystack/Flutterwave/Stripe **or** chooses pay-on-site.
5. Gets instant confirmation on WhatsApp + email + .ics calendar attachment.

**Workflow — Manage:** via magic link / customer portal → reschedule (within policy), cancel (refund per policy), rebook, view history, download receipts, manage notification/consent preferences, request data export/erasure.

**Reminders:** automated WhatsApp/SMS/email at configured intervals (e.g., 24h + 1h before) with one-tap confirm/reschedule.

---

## 5. Agency

**Who:** Web agency / reseller managing booking for many client businesses.

**Workflow:**
1. Holds an **Agency license** → unlocks multi-tenant console + white-label.
2. Onboards client sites/tenants; provisions per-client config from templates.
3. **White-labels:** replaces Bookora branding (name, logo, colors, "Powered by") per client.
4. Manages all clients from one console: switch tenant, bulk-update templates/policies, monitor health/delivery across clients.
5. **Billing:** marks up and rebills clients; tracks which clients are on which sub-plan; sees consolidated reporting.
6. Manages own sub-staff/operators with scoped access to assigned clients only.

---

## 6. Affiliate

**Who:** Marketer/creator/agency partner earning commission for referrals.

**Workflow:**
1. Joins affiliate program → gets dashboard + unique referral links/coupon codes.
2. Shares links; visits + signups attributed via cookie + server-side fallback (`AffiliateLedger`).
3. Tracks clicks → trials → conversions → recurring commission; sees pending vs cleared.
4. Requests payout (threshold-gated); sees payout history.
5. (Agency referral tier) earns higher/recurring rate for referring agencies; gets co-marketing assets.

---

## Cross-role interaction map

```
Customer ──books──▶ Booking Engine ──notifies──▶ Staff + Business Owner
   ▲                     │
   │                     ├─pays──▶ Payment Gateway ──ledger──▶ Affiliate
   └──reminders──────────┘
Administrator ──configures/licenses──▶ everything
Agency ──manages many──▶ {Business Owner, Staff} across tenants (white-label)
```
