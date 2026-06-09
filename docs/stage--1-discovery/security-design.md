# Bookora — Security Design

**Stage:** -1 · **Status:** Build complete · **Date:** 2026-06-05
Threat model · OWASP mapping · Security architecture · Data protection · GDPR/NDPR readiness · Audit logging.

---

## 1. Threat Model (STRIDE × assets)

**Key assets:** customer PII, payment data/secrets, gateway & OAuth credentials, booking integrity (no double-book/overbook), license/entitlement integrity, audit log integrity, tenant isolation.

| Threat (STRIDE) | Scenario | Mitigation |
|---|---|---|
| **Spoofing** | Forged webhook to mark unpaid booking paid | HMAC signature verify + timestamp window + idempotency; never trust client-claimed payment |
| **Spoofing** | Magic-link guessing / replay | Signed, short-TTL, single-use, scoped tokens; rate limit issuance |
| **Tampering** | Price/amount manipulation in booking request | Server recomputes price/tax/deposit; ignore client-sent totals |
| **Tampering** | Direct DB/object-id tampering (IDOR) | Tenant-scoping guard + ownership checks on every record access |
| **Repudiation** | "I never cancelled/refunded that" | Append-only audit log with actor, IP hash, before/after hashes |
| **Information disclosure** | PII leak across tenants / staff seeing others' revenue | Tenant + field-level scoping; least-privilege caps |
| **Information disclosure** | Secrets in responses/logs | Encrypt at rest; redact in logs; never serialize secrets to API |
| **Denial of service** | Booking/availability flooding | Token-bucket rate limits, soft-hold TTLs, bot mitigation, queue backpressure |
| **Elevation of privilege** | Staff hitting admin endpoints | Capability checks per endpoint + entitlement guard |
| **Elevation** | Free user invoking Pro/Agency features via API | `LicenseService` server-side enforcement (never client-trust) |

**Trust boundaries:** public widget ↔ REST (untrusted in); REST ↔ services (validated); services ↔ external gateways (signed, encrypted); agency console ↔ tenant data (scoped).

## 2. OWASP Top 10 (2021) Mapping

| OWASP | Risk | Bookora control |
|---|---|---|
| A01 Broken Access Control | IDOR, cross-tenant, privilege bypass | Central authz layer, tenant guard, ownership checks, default-deny |
| A02 Cryptographic Failures | Secrets/PII exposure | AES-GCM encryption at rest for tokens/secrets, TLS in transit, hashed identifiers |
| A03 Injection | SQLi/XSS | Prepared statements only (`$wpdb->prepare`), output escaping, no string-built SQL; CSP on admin SPA |
| A04 Insecure Design | Weak booking/payment flows | Threat-modeled flows, server-authoritative pricing, idempotency, state machine |
| A05 Security Misconfiguration | Debug leaks, open endpoints | Hardened defaults, no `WP_DEBUG` output in prod, least-privilege caps, security headers |
| A06 Vulnerable Components | Outdated deps | Pinned deps, automated `composer audit`/`npm audit` in CI, SBOM |
| A07 Auth Failures | Weak tokens, enumeration | Signed short-TTL magic links, nonce/JWT, rate-limited auth, generic errors |
| A08 Integrity Failures | Tampered updates/webhooks | Signed webhooks, signed license server responses, update signature checks |
| A09 Logging/Monitoring Failures | Blind to attacks | Audit log + security events + health dashboard + alerting |
| A10 SSRF | Malicious OAuth/webhook URLs | Allowlist external hosts, validate redirect URIs, no user-supplied fetch targets |

Plus **OWASP API Top 10** coverage (BOLA, broken auth, excessive data exposure, resource limits) handled by tenant guard, scoped serializers, and rate limits in [api-design.md](api-design.md).

## 3. Security Architecture

- **Defense in depth:** WP hardening → controller validation → service authz → repository scoping → DB constraints.
- **Secrets management:** gateway keys/OAuth tokens encrypted (libsodium/`openssl` AES-GCM) with a key derived from a site-specific salt + optional KMS; rotation supported.
- **Input/output:** sanitize on input (`sanitize_*`), validate types/ranges, escape on output (`esc_*`), nonce on state-changing requests.
- **Webhooks:** signature + replay protection + idempotency; isolated controllers; minimal trust.
- **Transport:** enforce HTTPS for payment/admin flows; HSTS recommended; secure+httpOnly+sameSite cookies.
- **Headers:** CSP, X-Content-Type-Options, X-Frame-Options/frame-ancestors, Referrer-Policy on admin surfaces.
- **Supply chain:** dependency pinning, audit in CI, code signing for distributed Pro package, no obfuscated code in free wp.org build.
- **Abuse/bot mitigation:** honeypot + optional Turnstile/reCAPTCHA on public booking, velocity checks, disposable-email/phone heuristics.

## 4. Data Protection Strategy

- **Classification:** PII (name/email/phone), financial (payments — minimize; rely on gateway tokenization, **never store full card data / PCI scope kept out by redirect/tokenized flows**), secrets, operational.
- **Minimization:** collect only what booking requires; configurable intake.
- **Encryption:** at rest for secrets/tokens + sensitive PII fields; TLS in transit.
- **Access:** least-privilege caps; tenant isolation; staff field-scoping.
- **Backups:** guidance + (hosted) encrypted backups; restore-tested.
- **PCI posture:** gateways handle card data (hosted/inline tokenized) → Bookora stays **SAQ-A class**; no PAN storage.

## 5. GDPR & NDPR Readiness

(NDPR = Nigeria Data Protection Regulation; GDPR = EU.)

| Requirement | Implementation |
|---|---|
| Lawful basis + consent | Consent capture at booking (`consent_json`), separable transactional vs marketing |
| Right to access / portability | `/customers/me/data-export` → machine-readable export |
| Right to erasure | `/customers/me/erasure` → anonymize within 30 days, retain legally-required financial records |
| Right to rectification | Customer portal edit + owner edit |
| Data retention | Configurable per data class (see [database-design.md](database-design.md) §5) |
| Processor records | DPA-ready: list sub-processors (Paystack, Flutterwave, Meta/WhatsApp, Google, etc.) |
| Breach response | Audit log + alerting + documented breach playbook (Stage 6) |
| Data residency | Self-hosted = customer-controlled; hosted relays document region (R-04) |
| DPIA | Templated DPIA for high-risk processing (health/medspa/clinic verticals) |

## 6. Audit Logging Strategy

- **Append-only** `wp_bkra_audit_log`: actor (type/id), action, entity + id, `before_hash`/`after_hash`, IP hash, UA hash, tenant, timestamp. No PII in the log body — hashes/ids only.
- **Logged actions:** auth events, permission changes, settings/integration changes, payments/refunds, manual booking edits, cancellations, data export/erasure, license activation, white-label changes, cross-tenant denials.
- **Integrity:** hash-chained entries (each row references prior hash) → tamper-evident.
- **Retention:** ≥ 24 months (compliance), then archive.
- **Surfacing:** admin audit viewer (capability-gated) + exportable for compliance review.
- **Separation:** security/audit events distinct from delivery/debug logs (which are short-retention, redacted).

---
**Verification plan:** static analysis (PHPStan/Psalm, ESLint), SAST + `composer/npm audit` in CI, dependency review, and a dedicated Security Audit at every stage gate. Pre-launch: external pen-test + the `/security-review` pass on the diff.
