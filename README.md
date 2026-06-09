# Bookora

Premium WordPress appointment-booking platform — **Paystack-native, Flutterwave-native, WhatsApp-native, Elementor-first**, and fast enough for a 3G connection.

> **Status:** Stage 1 — Project Foundation. See [`docs/master-build-spec.md`](docs/master-build-spec.md) for the full stage-gated roadmap and decision log.

## Tech stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.2+, WordPress 6.8+, WP REST API, custom tables (`wp_bkra_*`) |
| Admin UI | React 18 + TypeScript + Vite + Tailwind |
| Architecture | DDD `app/` tree, DI container, repository pattern, migration system |
| Standards | PSR-12 + WordPress Coding Standards, PHPStan |
| Tests | PHPUnit (+ WP test library), Jest |

## Layout

```
bookora.php        Plugin bootstrap
app/               Domain code (Core, Database, API, Admin, … )
assets/src/        React/TS admin source (built to assets/build/)
tests/             PHPUnit + Jest
docs/              Stage-gated specs, audits, strategy
```

## Development

```bash
# PHP deps + checks
php composer.phar install
php composer.phar run phpcs
php composer.phar run phpstan
php composer.phar run test          # requires a configured WP test library + MySQL

# JS build + checks
npm install
npm run build
npm run lint
npm run test
```

### Running the WordPress integration tests

The PHPUnit suite uses the WordPress test library and a throwaway MySQL database. Provision it with the standard installer script, then run `composer test`:

```bash
bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

(See `tests/phpunit/bootstrap.php` for the expected `WP_TESTS_DIR` / `WP_PHPUNIT__DIR` env vars.)

## License

GPL-2.0-or-later.
