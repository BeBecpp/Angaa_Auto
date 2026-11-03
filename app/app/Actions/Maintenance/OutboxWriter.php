<?php

namespace App\Actions\Maintenance;

use App\Enums\OutboxStatus;
use App\Models\Customer;
use App\Models\NotificationOutbox;
use App\Support\Phone;
use Carbon\CarbonImmutable;

/**
 * Inserts outbox rows. The unique idempotency key (aggregate + version + recipient + channel + stage)
 * is authoritative: re-running planning never creates a duplicate stage message.
 */
class OutboxWriter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function schedule(string $aggregateType, int $aggregateId, int $aggregateVersion, Customer $customer, string $templateKey, string $stage, CarbonImmutable $scheduledFor, array $payload, ?CarbonImmutable $expiresAt = null): ?NotificationOutbox
    {
        $phone = $customer->normalized_contact_phone;

        if ($phone === null) {
            return null;
        }

        $key = self::key($aggregateType, $aggregateId, $aggregateVersion, $phone, 'sms', $stage);

        $existing = NotificationOutbox::query()->where('idempotency_key', $key)->first();

        if ($existing) {
            return $existing;
        }

        return NotificationOutbox::query()->create([
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'aggregate_version' => $aggregateVersion,
            'customer_id' => $customer->id,
            'recipient_phone' => $phone,
            'channel' => 'sms',
            'template_key' => $templateKey,
            'template_version' => '1',
            'lifecycle_stage' => $stage,
            'scheduled_for' => $scheduledFor->utc(),
            'idempotency_key' => $key,
            'payload_minimal' => $payload,
            'status' => OutboxStatus::Scheduled,
            'expires_at' => $expiresAt?->utc(),
        ]);
    }

    public static function key(string $aggregateType, int $aggregateId, int $version, string $phone, string $channel, string $stage): string
    {
        return implode(':', [$aggregateType, $aggregateId, 'v'.$version, substr(Phone::hash($phone), 0, 16), $channel, $stage]);
    }
}
