<?php

namespace App\Console\Commands;

use App\Contracts\SmsProvider;
use App\Models\OpeningRule;
use App\Models\Service;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pass/warn/fail launch readiness report. Never prints secrets.
 */
#[Signature('app:readiness')]
#[Description('Check production readiness (environment, data, workers, SMS, backups)')]
class Readiness extends Command
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(SmsProvider $sms): int
    {
        $production = app()->isProduction();

        $this->check('APP_ENV', $production ? 'pass' : 'warn', 'Одоо: '.app()->environment().($production ? '' : ' (production биш)'));
        $this->check('APP_DEBUG', config('app.debug') ? ($production ? 'fail' : 'warn') : 'pass', config('app.debug') ? 'Идэвхтэй — production-д унтраана' : 'Унтраастай');
        $this->check('APP_KEY', filled(config('app.key')) ? 'pass' : 'fail', filled(config('app.key')) ? 'Тохируулсан (нууц тул харуулахгүй)' : 'Алга — php artisan key:generate');
        $this->check('HTTPS URL', str_starts_with((string) config('app.url'), 'https://') ? 'pass' : ($production ? 'fail' : 'warn'), (string) config('app.url'));
        $this->check('Demo/OTP viewer', $production && config('angaa.otp.local_viewer') ? 'warn' : 'pass', 'Local OTP viewer нь зөвхөн local/testing-д ажиллана');

        try {
            DB::connection()->getPdo();
            $this->check('Өгөгдлийн сан', 'pass', DB::connection()->getDriverName().' холбогдсон');
            $pending = collect(Artisan::call('migrate:status', ['--pending' => true]) === 0 ? explode("\n", Artisan::output()) : [])->filter(fn ($line) => str_contains($line, 'Pending'))->count();
            $this->check('Migration', $pending === 0 ? 'pass' : 'fail', $pending === 0 ? 'Бүгд ажилласан' : $pending.' хүлээгдэж байна');
        } catch (Throwable) {
            $this->check('Өгөгдлийн сан', 'fail', 'Холбогдож чадсангүй');
        }

        $owner = User::query()->where('type', 'staff')->where('staff_role', 'owner')->where('active', true)->exists();
        $this->check('Эзний бүртгэл', $owner ? 'pass' : 'fail', $owner ? 'Байна' : 'php artisan app:owner-create');

        $details = Settings::get('details_approved') && Settings::contactPhone() && Settings::get('address');
        $this->check('Бизнесийн мэдээлэл', $details ? 'pass' : 'fail', $details ? 'Утас, хаяг баталсан' : 'Утас/хаяг/баталгаа дутуу');
        $demoHours = OpeningRule::query()->where('is_demo', true)->exists() || OpeningRule::query()->count() === 0;
        $this->check('Ажлын цаг', $demoHours ? 'fail' : 'pass', $demoHours ? 'DEMO эсвэл хоосон — бодит цаг оруулна' : 'Бодит цаг');
        $demoServices = Service::query()->where('published', true)->where('is_demo', true)->exists();
        $this->check('Үйлчилгээ/үнэ', $demoServices ? 'fail' : 'pass', $demoServices ? 'DEMO үйлчилгээ нийтлэгдсэн байна' : 'OK');
        $demoData = DB::table('customers')->where('is_demo', true)->exists();
        $this->check('DEMO өгөгдөл', $demoData && $production ? 'fail' : ($demoData ? 'warn' : 'pass'), $demoData ? 'Зохиомол үйлчлүүлэгч байна' : 'Алга');

        try {
            $disk = Storage::disk('media');
            $disk->put('.readiness', 'ok');
            $disk->delete('.readiness');
            $public = is_link(public_path('storage')) && str_contains((string) realpath(public_path('storage')), 'private');
            $this->check('Нууц зургийн сан', $public ? 'fail' : 'pass', $public ? 'Нийтэд холбогдсон!' : 'Бичих боломжтой, нийтэд нээлттэй биш');
        } catch (Throwable) {
            $this->check('Нууц зургийн сан', 'fail', 'Бичих боломжгүй');
        }

        $this->check('Зураг хөрвүүлэлт', function_exists('imagecreatefromwebp') && function_exists('exif_read_data') ? 'pass' : 'warn', 'GD WebP/EXIF: '.(function_exists('imagecreatefromwebp') ? 'тийм' : 'үгүй').'/'.(function_exists('exif_read_data') ? 'тийм' : 'үгүй').'. HEIC: дэмжихгүй (тодорхой зөвлөгөө өгнө)');
        $limits = ini_get('upload_max_filesize').' / '.ini_get('post_max_size');
        $this->check('Upload хязгаар', 'pass', 'upload_max_filesize/post_max_size = '.$limits.' (8 MB зураг × 5)');

        foreach (['scheduler' => 'Scheduler (cron)', 'queue' => 'Queue worker'] as $name => $label) {
            $beat = SystemHeartbeat::query()->find($name)?->beat_at;
            $fresh = $beat && $beat->gt(now()->subMinutes(5));
            $this->check($label, $fresh ? 'pass' : 'fail', $beat ? 'Сүүлд: '.$beat->toIso8601String() : 'Хэзээ ч ажиллаагүй');
        }

        $smsState = match (true) {
            $sms->name() === 'log' => [$production ? 'fail' : 'warn', 'Туршилтын (log) горим — бодит SMS илгээхгүй. Порталын OTP production-д ажиллахгүй.'],
            $sms->isAvailable() => ['pass', $sms->name().' идэвхтэй (бодит хүргэлтийг гар утсаар шалгана)'],
            default => ['fail', 'SMS идэвхгүй — портал зөвхөн ажилтны горимд'],
        };
        $this->check('SMS', ...$smsState);

        $backup = config('angaa.backup.destination');
        $restore = Settings::get('last_restore_test_at');
        $this->check('Нөөцлөлт', $backup && $restore ? 'pass' : 'fail', ($backup ? 'Хаяг тохируулсан' : 'BACKUP_DESTINATION алга').' · сэргээх туршилт: '.($restore ?? 'хийгээгүй'));

        $manifest = is_file(public_path('build/manifest.json'));
        $this->check('Frontend build', $manifest ? 'pass' : 'fail', $manifest ? 'public/build байна' : 'npm run build');

        $this->table(['Шалгалт', 'Төлөв', 'Тайлбар'], $this->rows);
        $failed = collect($this->rows)->where(1, 'FAIL')->count();
        $this->line($failed === 0 ? 'Бэлэн.' : $failed.' шалгалт амжилтгүй — олон нийтэд нээхгүй.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $name, string $state, string $detail): void
    {
        $this->rows[] = [$name, strtoupper($state), $detail];
    }
}
