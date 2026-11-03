<?php

namespace App\Models;

use App\Enums\EstimateLineKind;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class InvoiceLine extends Model
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
