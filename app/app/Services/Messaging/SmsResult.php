<?php

namespace App\Services\Messaging;

final class SmsResult
{
    public const SIMULATED = 'simulated';

    public const ACCEPTED = 'accepted';

    public const KNOWN_FAILURE = 'known_failure';

    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $outcome,
        public readonly ?string $providerReference = null,
        public readonly ?string $errorCode = null,
        public readonly bool $retryable = false,
    ) {}

    public static function simulated(string $reference): self
    {
        return new self(self::SIMULATED, $reference);
    }

    public static function failure(string $errorCode, bool $retryable = false): self
    {
        return new self(self::KNOWN_FAILURE, null, $errorCode, $retryable);
    }

    public static function unknown(string $errorCode): self
    {
        return new self(self::UNKNOWN, null, $errorCode);
    }
}
