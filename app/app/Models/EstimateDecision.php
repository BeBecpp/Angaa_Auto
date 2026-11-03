<?php

namespace App\Models;

use App\Enums\DecisionChannel;
use App\Enums\EstimateDecisionType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class EstimateDecision extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => EstimateDecisionType::class,
            'channel' => DecisionChannel::class,
            'decided_at' => 'datetime',
            'amount_snapshot_mnt' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EstimateRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(EstimateRevision::class, 'estimate_revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
