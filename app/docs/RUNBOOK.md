# RUNBOOK — АНГАА АВТО

Nothing in this repository has been deployed. Server access, domain, SMS provider and credentials are separate,
explicitly authorized steps. Paths below are placeholders (`/srv/angaa-auto`) — use your server's real paths.

## 1. Runtime target

- PHP 8.4 FPM with: pdo_mysql, mbstring, intl, gd (with WebP), exif, fileinfo, zip, openssl, curl, bcmath.
- MySQL 8.4, `utf8mb4` / `utf8mb4_unicode_ci`, private network only, least-privilege user (no `GRANT ALL ON *.*`).
- Nginx (or Apache) with web root `app/public`. HTTPS required.
- Node is needed only at build time (`npm ci && npm run build`).
- `php.ini`: `upload_max_filesize = 20M`, `post_max_size = 100M`, `memory_limit = 512M` (GD decodes up to 16 MP images).

## 2. Production `.env` (no secrets in git)

```
APP_NAME="АНГАА АВТО"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<real-domain>
APP_KEY=<generated once with php artisan key:generate --show; store in a secret manager and keep a backup>
APP_LOCALE=mn
DB_CONNECTION=mysql
DB_HOST=<private-host>
DB_PORT=3306
DB_DATABASE=angaa_auto
DB_USERNAME=angaa_app
DB_PASSWORD=<secret>
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
SMS_DRIVER=disabled          # until a real provider adapter exists and is tested
OTP_LOCAL_VIEWER=false       # ignored outside local/testing anyway
BACKUP_DESTINATION=<description of encrypted off-server location>
```

`SMS_DRIVER=log` is a local simulation and reports FAIL in production readiness. With SMS disabled, customer OTP sign-in
shows an honest "unavailable" message; staff-only mode keeps working. There is no fallback universal OTP.

## 3. Deploy steps (per release)

```bash
cd /srv/angaa-auto/app
php artisan down --render="errors::503"
git pull                                 # or unpack the release
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan brand:derivatives
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize
php artisan queue:restart
php artisan up
php artisan app:readiness
```

Never run `app:demo-seed` in production (it refuses outside local/testing). Create the owner with `php artisan app:owner-create`.
Do not run `php artisan storage:link` for customer files — private media lives in `storage/app/private/media` and is served only
through `/media/{id}/{variant}` with authorization on every request.

Permissions: the PHP-FPM user owns `storage/` and `bootstrap/cache/`; everything else read-only. Never `chmod 777`.

Example configs: `deploy/nginx.conf.example`, `deploy/angaa-queue.service`, `deploy/angaa-crontab.example`.

## 4. Queue worker and scheduler

- Worker: systemd (or supervisor) running `php artisan queue:work --tries=3 --max-time=3600`, restarted on each release.
- Scheduler: cron every minute `php artisan schedule:run`. It writes a `scheduler` heartbeat; a heartbeat job proves the worker
  runs. Both are shown on the dashboard and in `app:readiness`. Reminders evaluate/dispatch every 5 minutes.
- If either stops, reminders silently stop — the dashboard shows the last heartbeat in red after 5 minutes.

## 5. Backup and restore

A database-only backup cannot restore photos, and a lost `APP_KEY` breaks encrypted session/cookie data.
Back up all three, encrypted, off-server, daily:

1. `mysqldump --single-transaction --routines --default-character-set=utf8mb4 angaa_auto | gzip | age -r <recipient> > db-YYYYMMDD.sql.gz.age`
2. `tar -C /srv/angaa-auto/app/storage/app/private -czf - media | age -r <recipient> > media-YYYYMMDD.tgz.age`
3. `.env` / APP_KEY copy in the secret manager (not in the same bucket as the data).

Never place backups under `public/`. Restore drill (staging, at least monthly): restore DB + media + `.env`, run
`php artisan migrate --force`, open a delivered work order in the portal and confirm the photos load. Then record the date in
Admin → Тохиргоо → Бизнесийн мэдээлэл → «Нөөцөөс сэргээж шалгасан огноо» (readiness checks it).

## 6. MySQL verification (required before release)

Local tests run on SQLite, which serializes writers with `IMMEDIATE` transactions plus a cross-process cache lock
(`App\Support\Serialized`). MySQL uses row locks: `ReservationGuard::lockResources()` locks bay then mechanic rows
(`SELECT … FOR UPDATE`, deterministic order) before checking overlaps, inside `DB::transaction(..., 3)` (deadlock retries).

This was **not executed** in the build environment (no MySQL/Docker available). To run it:

```bash
docker compose -f deploy/docker-compose.mysql.yml up -d
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33060 DB_DATABASE=angaa_test DB_USERNAME=angaa DB_PASSWORD=angaa \
  php artisan test --configuration=phpunit.mysql.xml
```

`ConcurrentBookingTest` uses SQLite files by design; for MySQL, run `tests/Support/confirm_slot_worker.php` twice concurrently
against the MySQL test database (same env vars) after seeding one advisor, one bookable mechanic, one bay, Monday hours and one
customer with a vehicle — exactly one must print `OK` and the other `CONFLICT`.

## 7. SQLite → MySQL data transfer (only if a real pilot ran on SQLite)

Never rename/copy the SQLite file. If only demo data exists, start production clean.
For real pilot data:

1. Back up the SQLite file and `storage/app/private/media`.
2. Create the empty MySQL schema: `php artisan migrate --force` (MySQL connection).
3. Export each table from SQLite in FK order (users, business_settings, services, shop_bays, staff_profiles, opening_rules,
   closures, customers, vehicles, vehicle_customer_access, odometer_readings, service_requests, appointments, work_orders,
   order_number_sequences, resource_reservations, work_order_status_events, inspection_items, estimate_revisions,
   estimate_lines, estimate_decisions, repair_items, qc_checks, media_files, invoices, invoice_lines, payments,
   payment_reversals, maintenance_plans, communication_consents, notification_outbox, message_attempts, audit_events,
   notifications) preserving `id`s; convert booleans to 0/1, JSON as text, timestamps as UTC `Y-m-d H:i:s`.
4. Insert with `SET FOREIGN_KEY_CHECKS=0` per batch, then re-enable and verify: row counts per table equal; `AUTO_INCREMENT`
   set above max(id); sample work orders render with estimates/payments.
5. Copy media files and compare `sha256` with `media_files.checksum` for every row.
6. Exclude rows with `is_demo = 1` (demo data must never become real customers).
7. Rehearse on staging first; keep the SQLite backup until the owner signs off.

## 8. Real SMS provider (future)

Implement `App\Contracts\SmsProvider` against the chosen provider's **current official API contract** (no guessed endpoints),
returning `simulated | accepted | known_failure | unknown`. Accepted ≠ delivered: mark `delivered` only from a verified receipt.
Timeouts must return `unknown` (reconcile in Admin → Сануулга → Мессежийн дараалал), never blind-retry. Test Cyrillic
segmentation and delivery on each Mongolian operator with owner-approved numbers before enabling OTP or reminders publicly.

QPay / ebarimt are out of scope: payments are manual records; there are no fake success endpoints.

## 9. Launch sequence

Local demo → owner review of real details (phone, address, map pin, hours, services/prices, bays/mechanics, callout policy,
warranty wording, approved photos) → staging on target PHP/MySQL → handset OTP/SMS + backup/restore drill →
small pilot with owner-selected customers → `app:readiness` all PASS → public launch.
