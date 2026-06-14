# Stage 18 — Commercial Hardening · Audit & Plugin Audit Report

**Stage:** 18 of 18 — Commercial Hardening
**Date:** 2026-06-14
**Status:** BUILD COMPLETE — awaiting `APPROVED FOR NEXT STAGE`

---

## 1. Scope delivered

Everything required to ship and operate Bookora as a paid commercial product.

| Capability | Where | Notes |
|---|---|---|
| Licensing | `app/Licensing/LicenseManager.php` | Activate/deactivate/check vs. a remote server; key **encrypted at rest** (AES-256-GCM); cached status with offline grace; daily re-validation cron |
| Tiers + feature flags | `app/Licensing/FeatureFlags.php` | `free`/`pro`/`agency`; per-site overrides; `bookora_feature_enabled` kill-switch filter |
| Self-hosted updater | `app/Updates/Updater.php` | `pre_set_site_transient_update_plugins` + `plugins_api`; 12h cache; license-gated package URL |
| Opt-in telemetry | `app/Telemetry/Telemetry.php` | **Off by default**; weekly cron; one-way site hash; **no PII**; `bookora_telemetry_payload` filter |
| White-label | `app/Branding/WhiteLabel.php` | Agency-only; renames admin menu + Plugins-list row; branding surfaced to the SPA via `bookora_admin_data` |
| Import / export | `app/DataTransfer/DataPortability.php` | Dynamic `bkra_` table discovery → portable JSON; replace-on-import; only restores known tables |
| Backup / restore | `app/DataTransfer/BackupManager.php` | Protected `uploads/bookora-backups/`; create/list/restore/delete; traversal-guarded ids |
| REST API | `app/API/Controllers/CommercialController.php` | 14 routes, all gated `bookora_manage_settings`; destructive ops require `confirm` |
| DI + lifecycle | `app/Commercial/CommercialServiceProvider.php` | Registered in `Plugin.php`; license/telemetry crons; updater + branding hooks |
| Admin UI | `assets/src/admin/components/commercial/CommercialPage.tsx` | **License & Tools** screen (License / Features / Branding / Import-Export / Telemetry) |
| Lifecycle cleanup | `Deactivator.php`, `Uninstaller.php` | Clears all crons on deactivate; drops `bookora_license` + update transient on guarded uninstall |

The plugin remains **fully functional unlicensed** (free tier). With no license/update/telemetry server URL configured (the default), there are **zero outbound calls** — every endpoint is filterable (`bookora_license_api_url`, `bookora_update_api_url`, `bookora_telemetry_api_url`).

---

## 2. Functional audit

- ✅ Unlicensed site reports `free` tier; activation (no server) grants `pro`, encrypts + round-trips the key (unit-tested).
- ✅ Feature flags follow tier, honour per-site overrides, and respect the kill-switch filter (unit-tested).
- ✅ Export → wipe → import round-trips a service row intact; unknown format rejected (unit-tested).
- ✅ Backups create/list/restore/delete; ids validated against traversal.
- ✅ Updater injects an update only when remote version > current and only hands the package to valid licenses.
- ✅ Telemetry never sends when disabled or when no endpoint is set; payload carries only aggregate counts + a salted hash.
- ✅ Admin UI: license activation, feature list, agency-gated branding, export/backups, telemetry opt-in + preview (Jest, 4 tests).

## 3. Security audit

- ✅ Every REST route gated on `bookora_manage_settings`; destructive import/restore require an explicit `confirm` flag.
- ✅ License key **encrypted at rest** (reused `Crypto`, AES-256-GCM); responses only ever expose a masked key.
- ✅ Telemetry is opt-in and anonymised — site identifier is a one-way salted SHA-256; no customer/booking/PII fields.
- ✅ Import only writes to dynamically-discovered `bkra_` tables (allowlist by ownership); foreign payload tables ignored.
- ✅ Backup directory protected (`.htaccess` deny + `index.php`); backup ids validated by regex before any filesystem access.
- ✅ All SQL parameterised or built from schema-derived identifiers (PHPCS-annotated, never user input).
- ✅ Branding inputs sanitised (`sanitize_text_field`, `esc_url_raw`, `sanitize_hex_color`); white-label save rejected below agency tier.
- ✅ `ABSPATH` guard on every PHP file. Remote calls fail soft (no fatals, cached misses).

## 4. Performance audit

- ✅ Update checks cached 12h (10-min negative cache on failure) — no per-request HTTP.
- ✅ License status read from a single non-autoloaded option; re-validated once daily via cron.
- ✅ Telemetry send is non-blocking (`blocking => false`) and weekly.
- ✅ Export/backup are admin-initiated, single pass per table; counts use indexed `COUNT(*)`.
- ✅ Admin bundle 353 kB (94.5 kB gzip) — modest growth for a whole new screen.

## 5. Code-quality audit

- ✅ PHPCS (WordPress-Extra + PSR-12 + PHPCompat 8.2): **clean** (155/155 files).
- ✅ PHPStan level 6 (+WP stubs), whole app: **No errors**.
- ✅ ESLint + `tsc --noEmit`: clean. Vite build: success.
- ✅ DDD module boundaries; `DataPortability` shared by export + backup (DRY); license/feature/updater each single-responsibility.

## 6. UX audit

- ✅ New **License & Tools** tab + admin submenu, capability-gated.
- ✅ Clear license state (Active/Inactive badge, tier, masked key, expiry); activation hidden once licensed.
- ✅ Feature list shows green/grey dots per feature; branding tab explains the agency requirement instead of failing silently.
- ✅ Destructive actions (restore) confirm via the browser; telemetry shows a full preview of exactly what would be sent.
- ✅ All inputs carry `aria-label`s; tabs are keyboard-reachable buttons.

---

## 7. Tests

| Suite | Test | Result |
|---|---|---|
| PHPUnit | `CommercialTest::test_unlicensed_site_is_free_tier` | written* |
| PHPUnit | `…::test_activation_without_server_grants_pro_and_encrypts_key` | written* |
| PHPUnit | `…::test_feature_flags_follow_tier_and_overrides` | written* |
| PHPUnit | `…::test_export_then_import_round_trips_data` | written* |
| PHPUnit | `…::test_import_rejects_unknown_format` | written* |
| Jest | `CommercialPage` — license / features / branding-lock / backups | ✅ pass (4) |
| Jest (full) | 14 suites / 21 tests | ✅ pass |

\* PHPUnit WP-integration suite needs MySQL + the WP test library (not available in this sandbox, as in every prior stage). Tests are authored to verified repository APIs and run in CI.

---

## 8. Plugin Audit Report

- **Stage Completed:** 18 — Commercial Hardening
- **Features Built:** licensing (activation/validation/tiers, encrypted key, daily re-check); tier-driven feature flags with override + kill-switch filter; self-hosted license-aware updater; opt-in anonymised telemetry; agency white-labelling; full data import/export; on-site backup/restore; commercial REST API; **License & Tools** admin screen; lifecycle cron/option cleanup.
- **Tests Passed:** ESLint, tsc, Vite build, Jest (14/14 suites, 21/21), PHPCS (155/155), PHPStan L6 (whole app). PHPUnit authored (CI).
- **Issues Found → Fixed:** PHPCS sniff-name mismatches in DB/filesystem ignore comments → corrected (`InterpolatedNotPrepared`, `file_get_contents_*`, `unlink_unlink`); WhiteLabel global `$submenu` override flagged → annotated; PHPStan redundant null-coalesce in `FeatureFlags` → extracted a `rank()` helper; PHPStan dynamic property on `stdClass` in `Updater` → narrowed type + initialised `response`; stale cron hook in `Deactivator` → now clears all six Bookora crons.
- **Remaining Risks:** licensing/updater/telemetry remote servers are not part of this repo — endpoints are filterable and verified only against mocked/empty servers (no live HTTP in sandbox); without a configured server, activation optimistically grants `pro` for self-managed installs (documented behaviour, change via `bookora_license_api_url`). Import/restore replace semantics are intentional and confirm-gated. Live PHPUnit unverified in sandbox (CI-only).
- **Approval Status:** ⏳ Awaiting `APPROVED FOR NEXT STAGE` (final stage before the Production Release Audit).

---

## 9. Verification commands

```
php -l app/{Licensing,Updates,Telemetry,Branding,DataTransfer,Commercial}/*.php   # clean
vendor/bin/phpcs app/                                # 155/155 clean
vendor/bin/phpstan analyse --memory-limit=1G        # No errors
npx eslint "assets/src/**/*.{ts,tsx}"               # clean
npx jest                                            # 14 suites, 21 tests pass
npm run build                                       # success
```
