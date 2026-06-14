# Bookora — User Guide

This guide walks a site administrator through configuring and operating Bookora.
All screens live under the **Bookora** menu in wp-admin.

## 1. First-run setup

1. **Services** — create the things customers book. Set name, duration, price,
   currency, capacity, and optional buffers before/after. Group them with
   categories.
2. **Staff** — add the people who deliver services. Assign each staff member to the
   services they offer, and set their weekly **working hours** plus any days off or
   special dates. Slot generation is time-zone aware.
3. **Payments** — enable Paystack, Flutterwave, and/or Stripe and paste your keys.
   Checkout is hosted-redirect, so no card data touches your site. Confirmation is
   driven by provider webhooks.
4. **Notifications** — enable Email/SMS/WhatsApp/Push, set reminder offsets (e.g.
   24 h and 1 h before), and edit templates.

## 2. Putting booking on your site

* **Shortcode:** add `[bookora_booking]` to any page for the multi-step booking
  wizard (service → staff → date/time → details → payment).
* **Elementor:** use the Bookora widgets — Booking Form, Service Grid, Staff Grid,
  Calendar, and Customer Dashboard.
* **Customer portal:** add `[bookora_portal]` to a page. Customers receive a
  magic-link email to view, reschedule, or cancel bookings — no account required.

## 3. Day-to-day operations

* **Calendar** — see and manage all appointments; create, reschedule, or cancel.
  The booking engine prevents double-booking, including under concurrent load.
* **Customers** — a lightweight CRM with tags and notes.
* **Reports** — KPIs, revenue by day, conversion, per-staff/per-service breakdowns,
  utilisation, and CSV export.

## 4. Calendar sync

Under **Integrations**, connect Google Calendar or Outlook per staff member via
OAuth. Sync is two-way; external busy times are merged into availability so staff
are never double-booked across calendars. Tokens are encrypted at rest.

## 5. Advanced features

* **Coupons** — percentage or fixed discounts with minimums, usage limits, expiry.
* **Gift cards** — issue and redeem with atomic balance debits.
* **Memberships / subscriptions** — recurring plans with member discounts; renewals
  run daily.
* **Resources** — rooms/equipment with capacity-aware availability.
* **Waitlist** — customers join when full and are auto-promoted on a cancellation.

## 6. AI scheduling

Under **AI Scheduling**:

* **Demand forecast** — projected bookings per day for the next two weeks.
* **Staff workload** — upcoming load per staff member, for balancing.
* **Smart suggestions** — enter a service and date range to get ranked slots,
  optionally biased to a preferred time of day.

## 7. License & tools

Under **License & Tools**:

* **License** — activate your key to unlock pro/agency features and updates.
* **Features** — see which features your tier enables.
* **Branding** *(agency)* — white-label the plugin name, vendor, logo, and colour.
* **Import / Export** — download a full data export, or create and restore on-site
  backups. Restores replace existing data and ask for confirmation.
* **Telemetry** — optionally share anonymous usage data (off by default); a preview
  shows exactly what would be sent.

## 8. Roles

Bookora installs three roles in addition to using the Administrator:

* **Bookora Manager** — full operational access.
* **Bookora Staff** — own schedule and bookings.
* **Bookora Customer** — portal access.

See the [developer guide](developer-guide.md) for the capability matrix.
