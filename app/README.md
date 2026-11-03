# АНГАА АВТО — авто сервисийн систем

Дархан дахь АНГАА АВТО-гийн нэг цехийн систем: зурагтай засварын хүсэлт → цаг (пост/механик) → хүлээн авалт →
онош/зураг → хувилбартай үнийн санал ба зөвшөөрөл → засвар → QC → нэхэмжлэл/гар төлбөр → олголт →
хувийн түүх → дараагийн үйлчилгээний сануулга → дахин захиалга.

Stack: **Laravel 13.39 · Filament 5.11 · Livewire 4.4 · Tailwind 4.3 · Vite 8 · FullCalendar 6.1.22 (MIT, standard) · PHP 8.4**.
Local: SQLite + database queue + private disk + SMS **log** driver (simulation only).

> Энэ бол local demo. SMS бодитоор илгээгдэхгүй, төлбөрийн систем холбогдоогүй, сервер рүү deploy хийгдээгүй.
> Бодит утас/хаяг/цаг/үнэ оруулаагүй — DEMO гэж тэмдэглэсэн. `php artisan app:readiness` нь юу дутуу байгааг харуулна.

## 1. Нэг удаагийн тохиргоо

Шаардлага: PHP 8.4 (ext: pdo_sqlite, sqlite3, mbstring, intl, gd, exif, fileinfo, zip, openssl, curl), Composer 2, Node 20+/npm.

**Bash (Linux/macOS/Git Bash)**

```bash
cd app
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan brand:derivatives        # handoff PNG-ээс вэб хэмжээний хуулбар/favicon
php artisan app:demo-seed            # зөвхөн local: зохиомол DEMO өгөгдөл
php artisan app:owner-create         # эзний бүртгэл (нууц үгийг асууна)
```

**PowerShell (Windows)**

```powershell
Set-Location .\app
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database\database.sqlite -Force | Out-Null
php artisan migrate
npm install
npm run build
php artisan brand:derivatives
php artisan app:demo-seed
php artisan app:owner-create
```

Брэндийн PNG-үүд `public/brand/`-д аль хэдийн суусан (`handoff/scripts/install-brand-assets.ps1 -ProjectRoot .\app`).
`app:demo-seed` нь DEMO ажилтны нууц үгийг гаргаж, мөн `storage/app/demo-credentials.txt`-д (git-д орохгүй) хадгална.
Шинээр эхлүүлэх: `php artisan app:demo-seed --fresh` (local өгөгдлийг устгана).

## 2. Ажиллуулах (4 процесс)

Нэг командаар: `composer dev` (= `php artisan dev`: server, queue, Vite, **scheduler**). Эсвэл тус тусад нь:

```bash
php artisan serve
npm run dev
php artisan queue:work --tries=3
php artisan schedule:work
```

Хаягууд: `/` сайт · `/huselt` хүсэлт · `/portal/nevtreh` үйлчлүүлэгч · `/admin` ажилтан · `/mechanic` механикийн утасны дэлгэц.
Queue/scheduler эсвэл компьютер зогсвол сануулга ажиллахгүй (dashboard-д улаанаар харагдана).

Local порталд нэвтрэх: DEMO утас (жишээ `0000 0004`) оруулахад SMS илгээгдэхгүй; кодыг **зөвхөн тухайн хөтчийн** нэвтрэх хуудсан дээр
«туршилтын орчин» хайрцагт харуулна (APP_ENV=local/testing үед л).

## 3. Төслийн командууд

| Команд | Үүрэг |
|---|---|
| `php artisan app:owner-create` | Эзний бүртгэл (нууц үг асууна, argument-аар өгөхгүй) |
| `php artisan app:demo-seed [--fresh]` | Зөвхөн local/testing: 10 хувилбар бүхий DEMO өгөгдөл |
| `php artisan reminders:dispatch --dry-run [--at="2027-04-28 10:00"]` | Юу илгээгдэхийг харуулна, юу ч бичихгүй |
| `php artisan reminders:dispatch` | Төлөвлөгөө шалгаж, outbox-ыг SMS драйвераар боловсруулна (local = simulated) |
| `php artisan app:readiness` | Нээлтэд бэлэн эсэх pass/warn/fail (нууц хэвлэхгүй) |
| `php artisan brand:derivatives` | Брэндийн PNG-ээс вэб хэмжээ, favicon |

## 4. Тест

```bash
php artisan test
```

80 тест / 424 assertion (2026-11-03-нд бүгд амжилттай). Үүнд хоёр тусдаа OS процесс нэг хоосон цагийг зэрэг баталгаажуулахад
нэг нь л амжилттай болох тест (SQLite). MySQL 8.4 дээр давтах заавар: `docs/RUNBOOK.md` §6.

## 5. Баримтууд

- `PROGRESS.md` — milestone, хэрэгжүүлсэн зам, тест, блок, дараагийн алхам
- `BRAND.md` — брэнд asset-ийг кодонд хэрхэн ашигласан
- `docs/RUNBOOK.md` — сервер, queue/cron, backup/restore, SQLite→MySQL, SMS провайдер
- `docs/ROLES.md` — эрхийн матриц
- `docs/RELEASE_CHECKLIST.md` — нээлтийн өмнөх жагсаалт
- `deploy/` — nginx, systemd, cron, MySQL docker-compose жишээ
