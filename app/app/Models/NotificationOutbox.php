<?php

namespace App\Models;

use App\Enums\OutboxStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
#[Table('notification_outbox')]
class NotificationOutbox extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OutboxStatus::class,
            'scheduled_for' => 'datetime',
            'payload_minimal' => 'array',
            'next_attempt_at' => 'datetime',
            'claimed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'expires_at' => 'datetime',
            'manual_call_completed_at' => 'datetime',
            'attempt_count' => 'integer',
            'aggregate_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<MessageAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(MessageAttempt::class);
    }
}
