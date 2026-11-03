<?php

namespace App\Models;

use App\Enums\EstimateLineKind;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class EstimateLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EstimateLineKind::class,
            'quantity_milli' => 'integer',
            'unit_price_mnt' => 'integer',
            'discount_mnt' => 'integer',
            'tax_mnt' => 'integer',
            'total_mnt' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EstimateRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(EstimateRevision::class, 'estimate_revision_id');
    }
}
