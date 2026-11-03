<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Append-only audit trail. Never pass OTPs, credentials, tokens or raw message bodies in context.
 */
class Audit
{
    private static ?string $correlationId = null;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $action, ?Model $subject = null, array $context = [], ?string $reason = null, ?int $actorId = null): AuditEvent
    {
        return AuditEvent::query()->create([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'context' => $context === [] ? null : $context,
            'reason' => $reason,
            'correlation_id' => self::correlationId(),
            'occurred_at' => now(),
        ]);
    }

    public static function correlationId(): string
    {
        return self::$correlationId ??= (string) Str::uuid();
    }
}
