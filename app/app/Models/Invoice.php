<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentState;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Invoice extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_snapshot' => 'array',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'is_demo' => 'boolean',
            'labor_total_mnt' => 'integer',
            'parts_total_mnt' => 'integer',
            'discount_total_mnt' => 'integer',
            'tax_total_mnt' => 'integer',
            'grand_total_mnt' => 'integer',
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
     * @return BelongsTo<EstimateRevision, $this>
     */
    public function estimateRevision(): BelongsTo
    {
        return $this->belongsTo(EstimateRevision::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Settled payments less reversals, in MNT.
     */
    public function paidNetMnt(): int
    {
        $settled = (int) $this->payments()->where('status', 'settled')->sum('amount_mnt');
        $reversed = (int) PaymentReversal::query()->whereIn('payment_id', $this->payments()->select('id'))->sum('amount_mnt');

        return $settled - $reversed;
    }

    public function balanceMnt(): int
    {
        if ($this->status !== InvoiceStatus::Issued) {
            return 0;
        }

        return max(0, $this->grand_total_mnt - $this->paidNetMnt());
    }

    public function paymentState(): PaymentState
    {
        $paid = $this->paidNetMnt();

        return match (true) {
            $paid <= 0 => PaymentState::Unpaid,
            $paid < $this->grand_total_mnt => PaymentState::PartPaid,
            default => PaymentState::Paid,
        };
    }
}
