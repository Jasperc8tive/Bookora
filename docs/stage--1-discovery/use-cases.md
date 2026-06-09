# Bookora — Use Case Specifications

**Stage:** -1 · **Status:** Build complete · **Date:** 2026-06-05
**Format:** Actors · Preconditions · Main flow · Alternate/Exception flows · Postconditions.

---

## UC-1 — Appointment Booking
**Actors:** Customer (primary), Booking Engine, Payment Gateway, Notification Dispatcher.
**Preconditions:** Service published; staff has availability; gateway connected (if paid).
**Main flow:**
1. Customer opens booking widget.
2. Selects service → staff/"any" → location → date.
3. Engine queries `AvailabilityEngine` → returns open slots (timezone-adjusted).
4. Customer selects slot → a **soft hold** is placed (TTL, e.g., 8 min).
5. Customer enters details + intake fields.
6. If paid: redirect/inline pay via Paystack/Flutterwave/Stripe.
7. Gateway webhook confirms payment → booking transitions `pending → confirmed`.
8. Dispatcher sends WhatsApp + email confirmation + .ics; staff notified.
**Alternate:** A4 slot taken during hold → re-query, ask to pick again. A6 pay-on-site → confirm immediately as `confirmed (unpaid)`.
**Exceptions:** Payment fails/timeout → hold released, booking `abandoned`. Webhook delayed → poll fallback + reconcile.
**Postconditions:** Slot reserved; payment recorded; notifications queued; audit entry written.

## UC-2 — Rescheduling
**Actors:** Customer or Business Owner/Staff, Booking Engine, Dispatcher, Calendar Sync.
**Preconditions:** Booking exists; within reschedule window per policy.
**Main flow:** 1. Open via magic link / dashboard. 2. Engine offers valid new slots. 3. Select new slot → atomic move (release old, hold+commit new). 4. Update calendar sync; notify customer + staff with diff.
**Alternate:** Outside policy window → blocked with reason / fee prompt (Pro). Price/duration differs → recompute, request balance.
**Exceptions:** New slot taken mid-transaction → retry/offer alternatives.
**Postconditions:** Single active appointment at new time; old slot freed; audit entry.

## UC-3 — Cancellation
**Actors:** Customer or staff, Booking Engine, Payment Gateway, Dispatcher.
**Preconditions:** Booking active.
**Main flow:** 1. Initiate cancel. 2. Policy engine evaluates window → refund eligibility (full/partial/none). 3. If refund due and paid online → gateway refund. 4. Status → `cancelled`; slot freed; waitlist promotion check; notify all parties.
**Exceptions:** Refund API failure → queue retry, flag for manual review.
**Postconditions:** Slot released; refund recorded; waitlist candidate notified; audit entry.

## UC-4 — Payments
**Actors:** Customer, Payment Gateway (driver), Booking Engine, Reconciliation.
**Preconditions:** Gateway connected; amount computed (price − coupon + tax, full/deposit).
**Main flow:** 1. Engine creates payment intent with idempotency key. 2. Customer pays. 3. Gateway → signed webhook → verify signature + amount + currency. 4. Mark `paid`; link to booking; emit `payment.succeeded`.
**Alternate:** Deposit → record balance due. Manual/cash → owner records offline payment.
**Exceptions:** Signature mismatch → reject + alert. Duplicate webhook → idempotency dedupe. Partial/over payment → flag reconciliation.
**Postconditions:** Immutable payment record; booking payment state updated; affiliate ledger credited if attributed.

## UC-5 — Notifications
**Actors:** Notification Dispatcher, channel drivers (WhatsApp/SMS/Email), Queue.
**Preconditions:** Event emitted (booking.confirmed, reminder.due, etc.); customer consent for channel.
**Main flow:** 1. Event → Dispatcher resolves template + recipient + channel priority. 2. Enqueue (Action Scheduler). 3. Worker renders template, sends via driver. 4. Record delivery status from provider callback.
**Alternate:** Primary channel fails → fallback chain (WA→SMS→email). Reminder cadence (24h/1h) scheduled at confirmation.
**Exceptions:** Provider rate-limit/error → backoff retry; permanent failure → mark + surface in health dashboard.
**Postconditions:** Delivery attempts logged; status visible; consent respected.

## UC-6 — Calendar Sync
**Actors:** Staff/Business Owner, Calendar Sync Service, Google/Microsoft APIs.
**Preconditions:** OAuth connected; scopes granted.
**Main flow:** 1. Booking created/changed → push event to external calendar. 2. External events pulled (incremental/webhook) → mapped to busy blocks → feed `AvailabilityEngine`. 3. Two-way reconciliation on a schedule.
**Exceptions:** Token expired → refresh; revoked → prompt reconnect, degrade gracefully (internal availability still authoritative).
**Postconditions:** Internal + external calendars consistent; no double-booking from external events.

## UC-7 — Staff Scheduling
**Actors:** Business Owner, Staff, Scheduling Engine.
**Preconditions:** Staff + services + locations defined.
**Main flow:** 1. Owner/staff set working hours, breaks, days off, holidays, per-service rules, buffers, caps. 2. Engine composes effective availability = working hours − breaks − time off − existing bookings − external busy − buffers, intersected with service rules and lead/notice constraints.
**Alternate:** Multi-location/multi-staff → "any available" resolves least-loaded or owner-defined priority.
**Exceptions:** Conflicting rules → deterministic precedence (time-off > working hours; explicit block > general rule).
**Postconditions:** Accurate bookable slots exposed to customers.

## UC-8 — Reporting
**Actors:** Business Owner/Agency, Reporting Service.
**Preconditions:** Historical bookings/payments exist.
**Main flow:** 1. Select range + filters (staff, service, location, channel). 2. Service aggregates from read-optimized rollups. 3. Render dashboards (revenue, occupancy, no-show, utilization, source). 4. Export CSV/PDF.
**Exceptions:** Large ranges → use pre-aggregated daily rollups to stay performant.
**Postconditions:** Insights delivered; exports generated; no PII leakage beyond role scope.

## UC-9 — Affiliate Tracking
**Actors:** Affiliate, Visitor, AffiliateLedger, Billing/License events.
**Preconditions:** Affiliate enrolled; referral link/code issued.
**Main flow:** 1. Visitor clicks referral link → cookie set + server-side click logged. 2. Signup/purchase event → attribution (last-touch by default, configurable window). 3. Ledger records commission (pending). 4. After clearance period → `cleared`. 5. Payout request above threshold → processed.
**Exceptions:** Cookie blocked → coupon-code fallback attribution. Refund within window → claw back commission.
**Postconditions:** Accurate, auditable commission ledger; payouts traceable.

## UC-10 — Agency Management
**Actors:** Agency operator, Tenant Provisioning, License/Entitlement Service.
**Preconditions:** Agency license active.
**Main flow:** 1. Operator creates/onboards a client tenant from a template. 2. Applies white-label branding + policies. 3. Assigns scoped sub-operators. 4. Monitors health/delivery + consolidated reporting across tenants. 5. Bulk-updates templates/policies; rebills clients.
**Exceptions:** Cross-tenant access attempt → denied by tenant-scoping guard + audit alert.
**Postconditions:** Isolated, branded, billable client environments managed from one console.

---

**Traceability:** each UC links to user stories in [user-stories.md](user-stories.md) and entities in [database-design.md](database-design.md); flows realized by services in [system-architecture.md](system-architecture.md).
