# PROGRESS — АНГАА АВТО

Last updated: 2026-11-03 (Asia/Ulaanbaatar). Spec: `../handoff/docs/01–09`. Brand: `../handoff/branding/`.

## Runtime and versions (actually installed)

| Component | Version |
|---|---|
| PHP | 8.4.27 NTS (winget `PHP.PHP.NTS.8.4`, Windows 11) — ext: pdo_sqlite, sqlite3, mbstring, intl, gd(+webp), exif, fileinfo, zip, openssl, curl, pdo_mysql |
| Composer | 2.11.1 (installer signature verified) |
| Node / npm | 24.15.0 / 11.12.1 |
| laravel/framework | 13.39.0 |
| filament/filament | 5.11.0 |
| livewire/livewire | 4.4.8 |
| tailwindcss / vite | 4.3.4 / 8.3.5 |
| @fullcalendar/* | 6.1.22 (pinned, MIT standard plugins only) |
| @fontsource/noto-sans | 5.3.0 (pinned, OFL) |
| laravel/boost (dev) | 2.10.4 |
| Database | SQLite (local, WAL, IMMEDIATE transactions); MySQL 8.4 **not available** in this environment |

`composer validate` OK · `composer check-platform-reqs` OK · locks: `composer.lock`, `package-lock.json`.

## Milestones

| Milestone | State | Implementation paths | Executed tests / evidence | Blockers |
|---|---|---|---|---|
| M0 bootstrap | verified | Laravel 13 + Filament 5 in `app/`; `config/angaa.php`; tokens in `resources/css/app.css`; `app/Providers/Filament/AdminPanelProvider.php`; private `media` disk; `app:owner-create`; `app:demo-seed` (local only) | AdminPagesTest (customer → /admin 403); `npm run build` OK; migrate:fresh OK | — |
| M1 request → staff → private history | verified | `app/Livewire/RequestWizard.php`, `app/Actions/Requests/SubmitServiceRequest.php`, `app/Services/Media/ImageProcessor.php`, `Filament/Resources/ServiceRequests`, `Actions/Customers/CustomerService.php`, `Http/Controllers/PortalController.php`, `MediaController` + `Policies/MediaFilePolicy.php`, `Actions/Auth/OtpService.php` | PublicRequestTest (10), PortalAccessTest (9) | Real handset camera/HEIC test is a manual pilot check |
| M2 calendar + reservations + check-in | verified (SQLite) | `Actions/Scheduling/*` (ReservationGuard, Confirm/Reschedule/Cancel, CheckInVehicle), `Support/Serialized.php`, `Filament/Pages/ScheduleCalendar.php`, `resources/js/calendar.js`, `Filament/Resources/Appointments` | SchedulingTest (12), ConcurrentBookingTest (2 real OS processes → 1 OK + 1 CONFLICT; negative control fails without locking) | **MySQL concurrency run not executed** (no MySQL/Docker here) — RUNBOOK §6 |
| M3 diagnosis → estimate → repair → QC → delivery | verified | `Actions/Workshop/*` (TransitionWorkOrder, EstimateService, RecordEstimateDecision, WorkshopService), `Livewire/Portal/EstimatePanel.php`, `Livewire/Mechanic/*`, `Filament/Resources/WorkOrders` | EstimateApprovalTest (11), EndToEndFlowTest | — |
| M4 finance + plans + reminders + reports | verified | `Actions/Finance/*`, `Support/Money.php`, `Actions/Maintenance/*` (EvaluateDuePlans, PlanAppointmentNotifications, DispatchOutbox, QuietHours, ConsentService, OutboxWriter), `Contracts/SmsProvider.php`, `Services/Messaging/*`, `reminders:dispatch`, `Support/Reports.php`, `Filament/Pages/ReportsPage.php` | FinanceTest (6), RemindersTest (17), unit NormalizationTest | Real SMS provider not chosen (log simulation only) |
| M5 product + release prep | verified locally | Public pages (`resources/views/public`), portal, mechanic screens, print templates (`resources/views/print`), dashboard widgets, settings, staff, audit, readiness (`app:readiness`), docs (`README.md`, `BRAND.md`, `docs/*`, `deploy/*`) | AdminPagesTest renders 28+ admin/mechanic/print URLs; browser checks below | Deployment, SMS, real business details |

## Test evidence (2026-11-03)

`php artisan test` → **81 passed, 433 assertions** (SQLite in-memory + file-based concurrency test), ~43 s.
`vendor/bin/pint` clean. `npm run build` OK.
`php artisan schedule:run` + `php artisan queue:work --once` → scheduler and queue heartbeats recorded.
`php artisan reminders:dispatch --dry-run` → lists due stages without writing; real run marks messages **simulated** only.
`php artisan app:readiness` (local) → expected FAILs: owner account, business details, DEMO hours/services, backup.

Browser checks on the real local server (Claude Browser pane, Chromium): home + request wizard at 390px (no horizontal scroll),
admin dashboard, calendar week view at 1440px (Mongolian labels, Monday start, overrun blocks), work order view,
customer OTP sign-in via the local viewer, pending estimate on phone width and approval click → success.
Defects found this way and fixed: unstyled demo banner in Filament, Title-Cased Mongolian nav labels, English FullCalendar
headers (no `mn` Intl data → explicit formatters), duplicated vehicle snapshot text, 584px-wide job page on phones
(estimate table → stacked list on small screens).

### Production install dry run (2026-11-03)

Fresh `git clone` → `bash app/deploy/install.sh` twice (APP_ENV=production, APP_DEBUG=false, SQLite variant, SMS disabled):
composer `--no-dev`, `npm ci && npm run build`, APP_KEY, migrations, brand derivatives, `optimize` (config/route/view caches),
`filament:optimize` all succeeded. Public pages 200, no DEMO banner, robots allows the public site only, portal sign-in shows
"unavailable" (no SMS). Found and fixed: signed-out visits to `/mechanic`, `/media/*`, print pages returned 500 (no `login`
route) → now redirect to the staff or portal sign-in (`bootstrap/app.php`, covered by AdminPagesTest).
`app:owner-create` worked; `app:readiness` FAILs only for server/business inputs (business details, hours, cron, worker, SMS, backup).
Repository holds no database, `.env`, uploads or demo data; the local database was reset to an empty schema.

## Traceability (spec → code → test)

| Requirement | Code | Test |
|---|---|---|
| Request is pending, never auto-linked by phone/plate | SubmitServiceRequest | PublicRequestTest::unverified_phone_and_plate… |
| Idempotent submit | unique (session hash, key) | PublicRequestTest::double_submit… |
| Image validation, EXIF strip, random private paths, 16 MP bound, HEIC advice | ImageProcessor | PublicRequestTest::invalid_files…, ::stored_photo… |
| Receipt only for submitting session | PublicPageController::receipt | PublicRequestTest::receipt… |
| Exactly-once conversion | ConfirmAppointment (+ unique service_request_id) | SchedulingTest::converting_a_request_twice… |
| Half-open intervals, buffers, hours, closures, bay OR mechanic conflicts | ReservationGuard | SchedulingTest |
| Concurrent same-slot booking | Serialized + lockResources + SQLite IMMEDIATE | ConcurrentBookingTest |
| Stale edit rejected, conflict reverts | RescheduleAppointment (version) / calendar.js revert | SchedulingTest::stale_version… |
| Viewer timezone independence | wall-clock event payload, timeZone UTC | SchedulingTest::business_time… |
| Overrun blocks occupied bay | openOccupancyOverlaps, CheckInVehicle::assertBayFree | SchedulingTest::overrun…, ::open_occupancy… |
| Immutable sent estimates, exact-revision approval, stale tab, double click | EstimateService, RecordEstimateDecision, EstimatePanel | EstimateApprovalTest |
| New scope needs new approval; decline keeps prior scope | EstimateService::publish, RecordEstimateDecision | EstimateApprovalTest::additional_scope… |
| Phone approval = staff attestation | DecisionChannel::Phone + note | EstimateApprovalTest::phone_approval… |
| Owner override audited | WorkshopService::ownerOverride | EstimateApprovalTest::unapproved_work… |
| Mechanic limits (no prices/publish/arbitrary jumps) | TransitionWorkOrder::MECHANIC_ALLOWED, policies | EstimateApprovalTest, AdminPagesTest::mechanic… |
| Integer MNT, single rounding, partial/final, idempotency, reversals ≤ payment | Money, PaymentService, InvoiceService | FinanceTest |
| Delivered ≠ paid; owner credit reason | WorkshopService::deliver | FinanceTest::delivery_with_balance… |
| Customer A/B isolation (orders, photos, prints, vehicles) | PortalController, MediaFilePolicy | PortalAccessTest |
| Ownership change isolation | CustomerService::endVehicleAccess, work_orders.customer_id scoping | PortalAccessTest::new_owner… |
| OTP hashed, session-bound, capped, generic errors; staff cannot use OTP | OtpService | PortalAccessTest::otp…, ::staff_phone… |
| Date OR km plans, unknown mileage, stale readings, km correction | EvaluateDuePlans, RecordOdometerReading | RemindersTest |
| Outbox idempotency, atomic claim, consent recheck, quiet hours, stale stages, simulated/accepted/unknown | OutboxWriter, DispatchOutbox, QuietHours, PlanAppointmentNotifications | RemindersTest |
| Full demo script through UI | Filament pages, Livewire portal/mechanic | EndToEndFlowTest (99 assertions) |
| CSV formula safety | Reports::csvSafe | NormalizationTest |

## Migrations

`0001_01_01_000000_create_users_table` (type, staff_role, phone), cache, jobs,
`2026_11_03_000100_create_catalog_and_customer_tables`, `2026_11_03_000200_create_request_schedule_workshop_tables`,
`2026_11_03_000300_create_finance_followup_security_tables`, notifications.

## Known limitations / decisions

- SQLite locking is a local strategy; MySQL row-lock path is implemented but unverified here.
- Portal account linking is staff-mediated by design (first OTP sign-in claims nothing).
- First-response metric counts wall-clock hours (not business hours).
- Inspection templates are a datalist of common items, not an authoritative schedule.
- Server-side PDF is not implemented; browser print-to-PDF (A4 print CSS) is provided.
- Owner MFA: not configured (Filament supports app MFA; recommended to enable for production after choosing a method).
- `composer dev` registers server, queue, Vite and scheduler; in an already-open shell started before PHP was installed,
  `php` may not be on PATH — open a new terminal.

## Remaining business details (owner)

Real phone, address/map pin, hours/holidays, services and prices, bays and mechanics, callout scope/fee, warranty policy,
approved public photos, domain, SMS provider choice and contract, backup destination.

## Next exact actions

1. Owner reviews the local demo (`php artisan app:owner-create`, then `/admin`).
2. Replace DEMO hours/services with real data; fill Бизнесийн мэдээлэл.
3. Run MySQL verification (RUNBOOK §6) on a machine with Docker/MySQL.
4. Choose an SMS provider; implement its adapter against the official API; handset tests.
5. Staging deploy with backups and a restore drill; then pilot.
