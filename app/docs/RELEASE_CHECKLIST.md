# Release checklist (before any public launch)

A passing local demo is not a business launch. Each item needs an owner or operator to tick it.

## Business details (owner)
- [ ] Real contact phone, address, map pin (lat/lng), landmark/parking directions entered in Бизнесийн мэдээлэл
- [ ] DEMO opening hours deleted; real hours, lunch break and holidays entered
- [ ] DEMO services unpublished/deleted; real services, price types, prices, durations, inclusions/exclusions entered
- [ ] Bays and bookable mechanics match the real workshop
- [ ] Callout enabled only if real: area and fee policy written
- [ ] Privacy and terms drafts reviewed (retention periods, contact) — no claim of legal certification
- [ ] Warranty wording decided per job (no generic warranty promise on the site)
- [ ] «Эзэн мэдээллийг баталсан» switched on
- [ ] Only owner-approved real photos used publicly; no customer repair photos as marketing without separate consent

## Platform (operator)
- [ ] Staging on PHP 8.4 + MySQL 8.4; `php artisan test --configuration=phpunit.mysql.xml` green
- [ ] Two-process same-slot booking verified on MySQL (RUNBOOK §6): one OK, one CONFLICT
- [ ] HTTPS, `APP_DEBUG=false`, `APP_ENV=production`, unique APP_KEY stored safely
- [ ] Queue worker (systemd/supervisor) and cron scheduler running; heartbeats fresh on dashboard
- [ ] Private media not web-accessible; `storage:link` not used for customer files
- [ ] Daily encrypted off-server backup of DB + media + .env; restore drill done and date recorded
- [ ] Real SMS adapter implemented against the provider's current API; Cyrillic text and delivery tested per operator
- [ ] Customer OTP tested on real iPhone and Android handsets; camera/gallery upload tested (HEIC rejected with advice)
- [ ] No demo data in production (`app:readiness` DEMO row PASS)
- [ ] `php artisan app:readiness` → all PASS

## Pilot
- [ ] 10 real jobs through request → approval → payment → history → reminder with a few owner-selected customers
- [ ] Issues found in pilot fixed before public promotion (Google Business Profile, Facebook, QR at the shop)
