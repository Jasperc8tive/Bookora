=== Bookora — Appointment Booking ===
Contributors: bookora
Tags: booking, appointments, scheduling, paystack, whatsapp
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The fastest WordPress appointment booking platform. Paystack-native, Flutterwave-native, WhatsApp-native, Elementor-first.

== Description ==

Bookora is a premium WordPress appointment-booking platform built for speed, African payment rails, and the messaging channels customers actually use. It runs on its own custom database tables (never posts/postmeta), a REST API, and a React admin — engineered to a senior enterprise standard.

**Booking & scheduling**

* Services with duration, price, capacity, buffers, categories, and per-staff assignment.
* Staff management with weekly working hours, day-off/special-date availability, and time-zone-aware slot generation.
* A conflict-free booking engine with booking holds (no double-booking under concurrency).
* A multi-step front-end booking wizard and an admin calendar.
* AI scheduling: smart slot suggestions, load-balancing auto-assignment, demand forecasting, and workload balance.

**Payments**

* Paystack, Flutterwave, and Stripe — hosted-redirect checkout (no card data on your site, SAQ-A).
* Webhook-authoritative confirmation with amount/currency match and idempotency.

**Notifications**

* Email, SMS, WhatsApp, and push channels with reminder offsets, dispatched asynchronously.

**Calendar sync**

* Two-way Google Calendar and Outlook (Microsoft Graph) sync, per-staff OAuth, tokens encrypted at rest.

**Customers & portal**

* A lightweight CRM and a stateless magic-link customer portal (reschedule, cancel, profile) — no WordPress user accounts required.

**Growth & advanced**

* Coupons, gift cards, memberships/subscriptions, resources, and a waitlist with promote-on-cancel.
* Reporting & analytics with CSV export.
* Elementor widgets for booking form, service/staff grids, calendar, and customer dashboard.

**Commercial**

* License tiers (free / pro / agency), a self-hosted updater, opt-in anonymised telemetry, agency white-labelling, full data import/export, and on-site backup/restore.

== Installation ==

1. Upload the `bookora` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload.
2. Activate the plugin through the 'Plugins' menu in WordPress. Activation creates the database tables and roles.
3. Open the **Bookora** menu in wp-admin and configure Services, Staff, Payments, and Notifications.
4. Add the booking wizard to any page with the `[bookora_booking]` shortcode (or the Elementor widgets), and the customer portal with `[bookora_portal]`.

Requires PHP 8.2+ and WordPress 6.8+.

== Frequently Asked Questions ==

= Does Bookora use posts or custom post types? =

No. All data lives in dedicated `wp_bkra_*` tables managed by a versioned migration system, keeping your `wp_posts` table clean and queries fast.

= Do customers need WordPress accounts? =

No. The customer portal uses a stateless, signed magic-link token — customers manage their bookings without a WordPress login.

= Is any data sent off my site by default? =

No. Telemetry is opt-in and anonymised, and the licensing/update endpoints are unset by default — there are no outbound calls until you configure them.

= How do I uninstall cleanly? =

Enable "delete data on uninstall" in settings before removing the plugin to drop all `wp_bkra_*` tables and options; otherwise data is retained.

== Changelog ==

= 1.0.0 =
* First production release. Full platform across 18 build stages: foundation, authorization/security, services, staff, customers, booking engine, booking wizard, admin calendar, payments (Paystack/Flutterwave/Stripe), notifications (email/SMS/WhatsApp/push), Google & Outlook calendar sync, Elementor integration, customer portal, reporting, advanced features (coupons/gift cards/memberships/resources/waitlist), AI scheduling, and commercial hardening (licensing, updater, telemetry, white-label, import/export, backup/restore).

= 0.1.0 =
* Stage 1: Project foundation — bootstrap, migrations, container, repository, settings, REST framework, logging, admin dashboard shell.

== Upgrade Notice ==

= 1.0.0 =
First production release. Back up your database before upgrading from a pre-release build.
