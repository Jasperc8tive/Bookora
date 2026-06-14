# Bookora — RC2 Remediation Sprint Audit

**From:** v1.0.0-RC1 → **To:** v1.0.0-rc2
**Date:** 2026-06-14
**Scope:** production stabilization only — resolve all Critical + High hostile-audit findings. No features, UX, or refactoring-for-aesthetics.
**Recommendation:** ✅ **APPROVED FOR RC2**

---

## 1. Resolved Findings Report

| ID | Severity | Finding | Status |
|---|---|---|---|
| HOTFIX-001 | Critical | Import/restore destroyed data (FK-unsafe `TRUNCATE`, no transaction, wrong order) | ✅ RESOLVED |
| HOTFIX-002 | High | Backups/logs world-readable on nginx/IIS; PII plaintext on disk | ✅ RESOLVED |
| HOTFIX-003 | High | Double-booking possible (ignored `GET_LOCK` result; unlocked `hold()`) | ✅ RESOLVED |
| HOTFIX-004 | High | Licensing open by default (no-server → pro); unsigned server trust | ✅ RESOLVED |
| HOTFIX-005 | High | "Encryption" silently fell back to plaintext; no rotation handling | ✅ RESOLVED |
| HOTFIX-006 | High | Uninstall left FK-referenced tables + PII dirs behind | ✅ RESOLVED |

### HOTFIX-001 — Import / Restore Data Integrity
- **Files:** `app/DataTransfer/DataPortability.php` (`import()`, new `ordered()`, `columns_for()`).
- **Fix:** single transaction with `FOREIGN_KEY_CHECKS=0`; `DELETE` (not `TRUNCATE`, which auto-commits) in reverse-dependency order; inserts parents-first with **schema-validated columns**; `ROLLBACK` + `bookora_import_failed` on any error → all-or-nothing.
- **Tests:** FK-chain round-trip restore; unknown-column filtering (injection guard); duplicate-PK failure → rollback preserves data; unsupported-format rejection.

### HOTFIX-002 — Secure Backup & Log Storage
- **Files:** new `app/Core/ProtectedDirectory.php`; `app/DataTransfer/BackupManager.php`; `app/Core/Logger.php`; `app/Commercial/CommercialServiceProvider.php`.
- **Fix:** unguessable per-site directory (HMAC of a site salt) + `.htaccess` (Apache) + `web.config` (IIS) + `index.php`; **backups encrypted at rest** via `Crypto`. nginx (no per-dir config) is covered by the unguessable path **and** at-rest encryption — an exposed file is ciphertext. Restore decrypts with legacy-plaintext fallback.
- **Tests:** on-disk contents are `enc:`-prefixed ciphertext (no plaintext PII); `.htaccess`/`web.config`/`index.php` present; encrypted backup restores.

### HOTFIX-003 — Booking Concurrency
- **Files:** `app/Appointments/BookingEngine.php` (`acquire_lock()`/`release_lock()`/`busy()`; `create`/`reschedule`/`hold` refactored).
- **Fix:** **fail-closed** — `GET_LOCK` must return exactly `1`; otherwise `409 bookora_busy` and the critical section is never entered. `hold()` now also serialized (was an unlocked TOCTOU). Authoritative post-lock conflict re-check retained. `wpdb` made injectable for deterministic testing.
- **Tests:** lock-unavailable → `bookora_busy` + zero writes; existing conflict/buffer/capacity/idempotency tests preserved.
- **Residual:** `GET_LOCK` is primary-connection-scoped — see Remaining Risks.

### HOTFIX-004 — Licensing Hardening
- **Files:** `app/Licensing/LicenseManager.php` (`remote()`, new `verify_signature()`, `activate()` default tier).
- **Fix:** **default-deny** — no server configured ⇒ `free` tier (never auto-`pro`). When `bookora_license_public_key` is set, server responses must carry a valid RSA-SHA256 `signature` or are rejected (`bookora_license_untrusted`). Tiering enforced by `FeatureFlags` on the verified tier.
- **Tests:** no-server → free + key still encrypted; mocked server → pro + flags enforce; unsigned response with key set → rejected, stays free.
- **Residual:** client-side checks are inherently bypassable by a modified binary — see Remaining Risks.

### HOTFIX-005 — Cryptography Hardening
- **Files:** `app/Security/Crypto.php`; bootstrap guard in `bookora.php`.
- **Fix:** **no plaintext fallback** — `encrypt()` throws if OpenSSL is missing; bootstrap fails soft (admin notice + bail) without OpenSSL. Versioned ciphertext (`enc:v1:`). Salt rotation ⇒ `decrypt()` returns `null` ⇒ callers re-authenticate (documented). Legacy `enc:`/`plain:` still readable for migration.
- **Tests:** round-trip; output is `enc:v1:` not plaintext; legacy `plain:`/`enc:` decrypt; garbage → null.

### HOTFIX-006 — Uninstall Integrity
- **Files:** `app/Core/Uninstaller.php`.
- **Fix:** drops wrapped in `SET FOREIGN_KEY_CHECKS=0 … =1` (order-independent, complete); recursively removes encrypted backup + log directories; options/transient/roles cleanup retained.
- **Tests:** flagged uninstall removes all FK-linked tables + options; unflagged uninstall is a no-op.

---

## 2. Remaining Risks Report

| Risk | Severity | Disposition |
|---|---|---|
| `GET_LOCK` advisory locks are primary-connection-scoped; active/active or multi-primary MySQL (Vitess/Galera) do not share them | Medium | Documented in `BookingEngine::acquire_lock()`. Single-primary (the default for WP) is fully protected. A distributed-lock backend is a post-RC2 scaling item, not a stabilization defect. |
| Client-side license enforcement is bypassable by a recompiled plugin | Low | Inherent to all client-side licensing; default-deny + signed responses raise the bar. Server-side entitlement checks would require a SaaS control plane (out of scope). |
| nginx cannot be configured by a dropped file | Low | Mitigated by unguessable directory path **and** at-rest encryption; a served backup file is ciphertext. Operators on nginx should still deny `/wp-content/uploads/bookora-*` at the server (documented). |
| Rate limiter remains a single per-IP global bucket (RC1 Medium M-1/M-2) | Medium | **Out of RC2 scope** (Medium finding); no regression. Tracked for a follow-up. |
| PHPUnit not executed in this environment (no MySQL/WP test lib) | Medium | 176 methods across 38 files authored to verified APIs; **CI must run green before tagging** — the standing release gate. |
| Live third-party HTTP (gateways, OAuth, license/update servers) verified against mocks only | Medium | Smoke-test in staging with real credentials before GA. |

No Critical or High findings remain open.

---

## 3. Regression Report

| Gate | Result |
|---|---|
| `composer phpcs` (app + tests) | ✅ exit 0 |
| `composer phpstan` (level 6, app scope) | ✅ No errors |
| `npm run lint` (ESLint, zero-warnings) | ✅ exit 0 |
| `npm run test` (Jest) | ✅ 14 suites / 21 tests |
| `npm run build` (tsc + Vite) | ✅ exit 0 |
| PHPUnit method count | 176 (was 163) across 38 files |

No new Critical issues introduced. Removed dead code (`with_staff_lock`); confirmed no stale references; no `TRUNCATE` remains in the codebase.

---

## 4. Security Report

- **Data at rest:** backups now AES-256-GCM encrypted; OAuth tokens + license key encryption mandatory (OpenSSL required, no plaintext fallback).
- **Storage exposure:** private dirs hardened for Apache/IIS + unguessable path for nginx.
- **Authorization/AuthN:** unchanged and intact (cap-gated REST, portal HMAC tokens).
- **Licensing:** default-deny + signed-response tamper resistance.
- **Concurrency:** fail-closed locking removes the double-booking exploit.
- **Teardown:** complete, FK-safe data removal on uninstall.
- **Injection:** import column allow-listing closes the uploaded-key→SQL path.

## 5. Performance Report

- Import uses `DELETE` within one transaction (admin-initiated; acceptable).
- Backup encryption cost only on admin backup/restore.
- Booking lock unchanged in the happy path (5 s bounded wait, immediate fail-closed otherwise).
- License signature verification: one `openssl_verify` only when a public key is configured.
- No new queries on hot paths; asset bundles unchanged.

## 6. Release Recommendation

### ✅ APPROVED FOR RC2

All Critical (1) and High (5) findings are **RESOLVED** with code, tests, and audits. Static-analysis, lint, JS-test, and build gates are green; the PHPUnit suite (176 methods) is the standing CI gate before tagging. Remaining items are Medium/Low and either out of the stabilization scope or documented operational guidance — none block RC2.

**Pre-tag gate:** green PHPUnit run in CI (MySQL + WP test library).
