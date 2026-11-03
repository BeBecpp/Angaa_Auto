<?php

namespace App\Services\Messaging;

use App\Contracts\SmsProvider;
use App\Support\Phone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Local/testing simulation. Nothing leaves the machine; the outcome is always "simulated".
 * Logs only a masked destination and the template length, never the body (may contain codes).
 */
class LogSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function isAvailable(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    public function send(string $destination, string $body, string $idempotencyKey): SmsResult
    {
        if (! $this->isAvailable()) {
            return SmsResult::failure('sms_provider_unavailable');
        }

        Log::info('SMS simulated (not sent)', [
            'to' => Phone::mask($destination),
            'length' => mb_strlen($body),
            'key' => substr(hash('sha256', $idempotencyKey), 0, 12),
        ]);

        return SmsResult::simulated('sim-'.Str::lower(Str::random(10)));
    }
}
