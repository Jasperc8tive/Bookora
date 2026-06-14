# Bookora — Installation, Upgrade & Uninstall

## Requirements

| | Minimum |
|---|---|
| PHP | 8.2 |
| WordPress | 6.8 |
| MySQL / MariaDB | InnoDB (for foreign keys) |
| PHP extensions | `openssl` (secret encryption), `json` |

If PHP is below 8.2 the plugin fails soft — it shows an admin notice and halts
rather than fataling.

## Install

1. Upload the `bookora` folder to `/wp-content/plugins/`, or install the ZIP via
   **Plugins → Add New → Upload Plugin**.
2. **Activate.** On activation Bookora:
   * runs all database migrations, creating the `wp_bkra_*` tables,
   * installs the Bookora roles and grants the Administrator every Bookora capability,
   * seeds default settings,
   * records the installed version and DB version.
3. Open **Bookora** in wp-admin and follow the [user guide](user-guide.md).

## Upgrade

* Updates are delivered through Bookora's self-hosted updater once a license is
  active (premium builds are not on wp.org). Update from **Dashboard → Updates** or
  **Plugins** like any other plugin.
* On load, Bookora compares the stored DB version to the code and **auto-runs any
  pending migrations** — migrations are idempotent and forward-only in normal use.
* **Always back up your database before a major upgrade.** You can also create an
  on-site snapshot first under **License & Tools → Import / Export → Create backup**.

## Configuration constants & filters

Point Bookora's commercial services at your own infrastructure (all empty by
default → no outbound calls):

```php
add_filter( 'bookora_license_api_url',   fn () => 'https://licenses.example.com' );
add_filter( 'bookora_update_api_url',    fn () => 'https://updates.example.com' );
add_filter( 'bookora_telemetry_api_url', fn () => 'https://telemetry.example.com' );
```

## Deactivate

Deactivation is non-destructive: it clears Bookora's scheduled cron events and
flushes rewrite rules. **No data is deleted.**

## Uninstall

Uninstall is safe by default — **data is retained**. To remove everything, first
enable **Settings → delete data on uninstall**, then delete the plugin. That drops
all `wp_bkra_*` tables, removes Bookora options and the license record, clears the
update cache, and removes the Bookora roles/capabilities.

## Backup & restore

* **Export** (`License & Tools → Import / Export`) downloads a portable JSON
  document of settings + all data tables.
* **Backups** are stored in `wp-content/uploads/bookora-backups/` (protected from
  direct web access). Create, restore, or delete them from the same screen.
* **Restore** and **Import** replace existing data and require explicit
  confirmation.

## Troubleshooting

* **Tables missing after activation** — ensure the DB user can `CREATE TABLE`; check
  the log directory `wp-content/uploads/bookora-logs/`.
* **Payments not confirming** — verify the gateway webhook URL and secret; Bookora
  only marks a booking paid from a verified webhook.
* **Calendar not syncing** — re-connect OAuth under Integrations; external busy data
  is cached and refreshed on a schedule.
