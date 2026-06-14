# Bookora — CI/CD & Release Automation

Every commit, pull request, release candidate, and production release passes
through GitHub Actions quality gates. Nothing ships that hasn't been built,
linted, statically analysed, and tested.

## Workflows

| Workflow | File | Trigger | Purpose |
|---|---|---|---|
| **CI** | `.github/workflows/ci.yml` | push, pull_request, `workflow_call` | Full quality gate: PHPCS, PHPStan L6, ESLint, Jest, Vite build, PHPUnit (PHP 8.2/8.3/8.4 × WP latest/6.8 on MySQL 8) |
| **RC Validation** | `.github/workflows/rc-validation.yml` | tag `*-rc*` | Full CI → build → package validation → upload `bookora.zip` artifact |
| **Production Release** | `.github/workflows/release.yml` | tag `v[0-9]+.[0-9]+.[0-9]+` | Full CI + security scan → build/package validation → publish GitHub Release assets |
| **Dependency Audit** | `.github/workflows/dependency-audit.yml` | weekly (Mon 06:00 UTC) + manual | `composer audit` + `npm audit`, publishes a security report, fails on high/critical |
| **Code Quality Report** | `.github/workflows/code-quality.yml` | push to `main`, PR, manual | PHPCS/PHPStan reports + PHPUnit & Jest coverage as artifacts |

### Gate design

CI is split into two **required** jobs:

- **`static`** (PHP 8.2, once): PHPCS, PHPStan, ESLint, Jest, Vite build — these
  are PHP/WordPress-version independent, so running them once is correct and
  fast. A failure fails the whole run.
- **`phpunit`** (matrix PHP 8.2/8.3/8.4 × WP latest/6.8, MySQL 8): the
  integration suite, where version compatibility actually matters.

A push/PR is green only when **both** jobs pass. `matrix.fail-fast: false` so all
cells report. WordPress `6.8` is the supported floor (`Requires at least: 6.8`)
and serves as "previous stable"; `latest` tracks the current release.

The CI workflow is reusable (`workflow_call`); RC and Release workflows invoke it
so the exact same gate runs before any artifact is produced.

## Local development workflow

Mirror the CI gates before pushing:

```bash
# PHP
composer install
composer phpcs        # WordPress-Extra + PSR-12 + PHPCompatibility (8.2-)
composer phpstan      # level 6 + WP stubs
composer test         # PHPUnit — needs the WP test library + MySQL (see below)

# JS
npm ci
npm run lint          # ESLint, zero warnings
npm test              # Jest
npm run build         # tsc --noEmit && vite build
```

### Running PHPUnit locally

PHPUnit needs the WordPress test library and a MySQL database:

```bash
bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
composer test
```

`bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]`
downloads WordPress + the test suite and writes `wp-tests-config.php`. The plugin
bootstrap (`tests/phpunit/bootstrap.php`) reads `WP_TESTS_DIR`.

## Required secrets

None beyond the automatically-provided **`GITHUB_TOKEN`** (used by the release
workflow to publish release assets). No third-party tokens are required for CI,
RC, audits, or coverage. The license/update/telemetry endpoints are **not**
exercised in CI.

If you later host a private packagist/npm registry, add the relevant tokens as
repository secrets and reference them in the install steps — no workflow change
is needed today.

## Build & package

`bin/build-release.sh` produces `dist/bookora.zip` containing only runtime files
(built bundle + production Composer autoloader), honouring `.distignore`.
`bin/validate-package.sh` asserts the package includes the required runtime files
(`bookora.php`, `app/`, `vendor/autoload.php`, `assets/build/`) and excludes all
dev artifacts (`tests/`, `node_modules/`, `assets/src/`, `docs/`, dev configs),
and that no dev dependency (e.g. PHPUnit) leaked into the package. RC and Release
workflows run both and fail on any violation.

## Release process

### Release candidate

1. Ensure `main` is green (CI passed).
2. Set the version in `bookora.php` (`Version:` header **and** `BOOKORA_VERSION`)
   and `package.json` to e.g. `1.0.0-rc3`.
3. Commit, then tag and push:
   ```bash
   git tag v1.0.0-rc3
   git push origin v1.0.0-rc3
   ```
4. **RC Validation** runs the full CI gate, builds, validates, and uploads
   `bookora.zip` as a workflow artifact for QA/staging.

### Production (GA)

1. Bump the version to the GA value (e.g. `1.0.0`) in `bookora.php` +
   `package.json` + `readme.txt` (`Stable tag`).
2. Tag and push a **GA semver** tag (no suffix):
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```
3. **Production Release** runs full CI + security scan, verifies the tag matches
   `BOOKORA_VERSION` (fails on mismatch), builds + validates the package, and
   publishes a GitHub Release with `bookora.zip` + its SHA-256 checksum.

## Tagging process

- **RC tags:** `vX.Y.Z-rcN` → matched by `*-rc*` → RC Validation only.
- **GA tags:** `vX.Y.Z` → matched by `v[0-9]+.[0-9]+.[0-9]+` → Production Release
  only.

The two patterns are mutually exclusive, so an RC tag never triggers a GA
release and vice-versa. The release workflow additionally enforces tag ⇄
`BOOKORA_VERSION` parity.

## Rollback process

A WordPress plugin release is rolled back by re-releasing the previous good
version (WordPress always installs the version the update server advertises):

1. **Identify** the last good tag (`git tag --list 'v*' --sort=-v:refname`).
2. **Re-publish** it as the current release:
   - In the GitHub Releases UI, mark the bad release as a pre-release/draft and
     set the previous release as "Latest", **or**
   - cut a new patch tag from the last good commit:
     ```bash
     git checkout v1.0.0            # last good
     # bump to v1.0.1 in bookora.php + package.json + readme.txt
     git commit -am "Roll back to 1.0.0 codebase as 1.0.1"
     git tag v1.0.1 && git push origin v1.0.1
     ```
3. The Production Release workflow rebuilds and republishes the asset; sites
   update to the rolled-back build through the normal update channel.
4. **Data:** if a release shipped a migration, roll **forward** with a corrective
   migration — never hand-edit production tables. Use **License & Tools →
   Backups** (encrypted, atomic restore) before any risky upgrade.

> Re-tagging the *same* version is discouraged; always roll forward to a new
> version number so update servers and caches behave predictably.

## Verifying workflows

- YAML is validated locally (`js-yaml` parse) and shell helpers with `bash -n`.
- Actual execution occurs on GitHub when commits/tags are pushed. Watch the
  **Actions** tab; the first push after merging this pipeline exercises CI end to
  end, and the first `*-rc*`/`v*` tag exercises the RC/Release paths.
