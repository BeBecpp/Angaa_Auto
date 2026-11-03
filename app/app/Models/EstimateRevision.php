<?php

namespace App\Models;

use App\Enums\EstimateState;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded(['id'])]
class EstimateRevision extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => EstimateState::class,
            'valid_until' => 'date',
            'published_at' => 'datetime',
            'labor_total_mnt' => 'integer',
            'parts_total_mnt' => 'integer',
            'discount_total_mnt' => 'integer',
            'tax_total_mnt' => 'integer',
            'grand_total_mnt' => 'integer',
            'revision_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return HasMany<EstimateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class);
    }

    /**
     * @return HasOne<EstimateDecision, $this>
     */
    public function decision(): HasOne
    {
        return $this->hasOne(EstimateDecision::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isImmutable(): bool
    {
        return $this->state !== EstimateState::Draft;
    }
}
