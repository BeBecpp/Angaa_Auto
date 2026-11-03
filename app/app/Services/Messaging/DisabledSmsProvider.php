<?php

namespace App\Services\Messaging;

use App\Contracts\SmsProvider;

/**
 * Used when no verified real provider is configured. Always a visible known failure, never fake success.
 */
class DisabledSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function send(string $destination, string $body, string $idempotencyKey): SmsResult
    {
        return SmsResult::failure('sms_provider_unavailable');
    }
}
