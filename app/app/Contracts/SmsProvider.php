<?php

namespace App\Contracts;

use App\Services\Messaging\SmsResult;

/**
 * Outbound SMS transport. Implementations must return a truthful outcome:
 * simulated (no network), accepted (provider took it — NOT handset delivery), known_failure
 * (definitely not sent), or unknown (timeout/ambiguous — reconcile, never blind retry).
 */
interface SmsProvider
{
    public function name(): string;

    public function isAvailable(): bool;

    public function send(string $destination, string $body, string $idempotencyKey): SmsResult;
}
