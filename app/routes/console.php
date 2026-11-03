<?php

use App\Actions\Auth\OtpService;
use App\Jobs\QueueHeartbeat;
use App\Models\MediaFile;
use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| Scheduler (run `php artisan schedule:work` locally, or a once-per-minute `schedule:run` cron in production).
| Reminders only run while the scheduler and a queue worker are running.
*/

Schedule::call(fn () => SystemHeartbeat::beat('scheduler'))->everyMinute()->name('scheduler-heartbeat');
Schedule::job(new QueueHeartbeat)->everyMinute()->name('queue-heartbeat');

Schedule::command('reminders:dispatch')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

Schedule::call(fn () => OtpService::purgeExpired())->daily()->name('otp-purge');

/*
| Orphaned request photos (upload stored but request never committed) and Livewire temp files.
*/
Schedule::call(function (): void {
    $disk = Storage::disk(config('angaa.uploads.disk'));
    $cutoff = now()->subHours((int) config('angaa.uploads.abandoned_grace_hours'))->getTimestamp();
    $known = MediaFile::query()->pluck('private_path')->merge(MediaFile::query()->pluck('preview_path'))->filter()->flip();

    foreach ($disk->allFiles() as $path) {
        if (! isset($known[$path]) && $disk->lastModified($path) < $cutoff) {
            $disk->delete($path);
        }
    }
})->daily()->name('media-orphan-cleanup');
