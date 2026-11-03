<?php

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Services\Messaging\DisabledSmsProvider;
use App\Services\Messaging\LogSmsProvider;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsProvider::class, function (): SmsProvider {
            return match (config('angaa.sms.driver')) {
                'log' => new LogSmsProvider,
                default => new DisabledSmsProvider,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('mn');
        CarbonImmutable::setLocale('mn');

        $proxies = config('angaa.trusted_proxies');

        if (filled($proxies)) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));
        }

        // `composer dev` / `php artisan dev`: add the scheduler next to server, queue and Vite (reminders need it).
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('schedule:work', 'scheduler');
        }

        RateLimiter::for('public-forms', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('media', fn (Request $request): Limit => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
    }
}
