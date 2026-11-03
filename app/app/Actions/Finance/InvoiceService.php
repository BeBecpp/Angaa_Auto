<?php

namespace App\Actions\Finance;

use App\Enums\EstimateLineKind;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\EnumValue;
use App\Support\Money;
use App\Support\Sequence;
use App\Support\Serialized;

/**
 * Invoice drafts are built from the approved estimate scope; issuing freezes lines, totals and a
 * snapshot and assigns a number. Not a tax/ebarimt receipt.
 */
class InvoiceService
{
    public function createDraft(WorkOrder $order, User $actor): Invoice
    {
        $this->assertFinance($actor);

        return Serialized::run('invoice:'.$order->id, function () use ($order, $actor): Invoice {
            $existing = $order->invoices()->whereIn('status', [InvoiceStatus::Draft, InvoiceStatus::Issued])->latest('id')->first();

            if ($existing) {
                return $existing;
            }

            $revision = $order->currentApprovedRevision;

            if ($revision === null) {
                throw new DomainException('Зөвшөөрсөн үнийн санал байхгүй тул нэхэмжлэл үүсгэх боломжгүй.');
            }

            $invoice = $order->invoices()->create([
                'estimate_revision_id' => $revision->id,
                'status' => InvoiceStatus::Draft,
                'is_demo' => $order->is_demo,
            ]);

            foreach ($revision->lines()->orderBy('sort_order')->get() as $line) {
                $invoice->lines()->create($line->only(['kind', 'description', 'quantity_milli', 'unit_price_mnt', 'discount_mnt', 'tax_mnt', 'total_mnt', 'sort_order']));
            }

            $this->recalculate($invoice);
            Audit::record('invoice.draft_created', $invoice, actorId: $actor->id);

            return $invoice->refresh();
        });
    }

    /**
     * @param  list<array{kind: string, description: string, quantity: string|int|float, unit_price_mnt: int, discount_mnt?: int}>  $lines
     */
    public function saveDraftLines(Invoice $invoice, array $lines, User $actor, ?string $note = null): Invoice
    {
        $this->assertFinance($actor);

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainException('Гаргасан нэхэмжлэлийг өөрчлөх боломжгүй.');
        }

        $invoice->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $quantity = Money::quantityToMilli($line['quantity']);
            $unit = (int) $line['unit_price_mnt'];
            $discount = (int) ($line['discount_mnt'] ?? 0);

            $invoice->lines()->create([
                'kind' => EstimateLineKind::from(EnumValue::of($line['kind'])),
                'description' => trim($line['description']),
                'quantity_milli' => $quantity,
                'unit_price_mnt' => $unit,
                'discount_mnt' => $discount,
                'tax_mnt' => 0,
                'total_mnt' => Money::lineTotal($quantity, $unit, $discount),
                'sort_order' => $index,
            ]);
        }

        $invoice->forceFill(['note' => $note])->save();
        $this->recalculate($invoice);

        return $invoice->refresh();
    }

    public function issue(Invoice $invoice, User $actor): Invoice
    {
        $this->assertFinance($actor);

        return Serialized::run('invoice:'.$invoice->work_order_id, function () use ($invoice, $actor): Invoice {
            $fresh = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === InvoiceStatus::Issued) {
                return $fresh;
            }

            if ($fresh->status !== InvoiceStatus::Draft) {
                throw new DomainException('Хүчингүй нэхэмжлэлийг гаргах боломжгүй.');
            }

            if ($fresh->lines()->count() === 0) {
                throw new DomainException('Нэхэмжлэлд мөр алга.');
            }

            $this->recalculate($fresh);
            $fresh->refresh();
            $order = $fresh->workOrder;

            $fresh->forceFill([
                'status' => InvoiceStatus::Issued,
                'invoice_number' => Sequence::formatted('INV'),
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'issue_snapshot' => [
                    'order_number' => $order->order_number,
                    'customer' => $order->customer_snapshot,
                    'vehicle' => $order->vehicle_snapshot,
                    'lines' => $fresh->lines()->orderBy('sort_order')->get()->map->only(['kind', 'description', 'quantity_milli', 'unit_price_mnt', 'discount_mnt', 'tax_mnt', 'total_mnt'])->all(),
                    'grand_total_mnt' => $fresh->grand_total_mnt,
                ],
            ])->save();

            Audit::record('invoice.issued', $fresh, ['total' => $fresh->grand_total_mnt], actorId: $actor->id);

            return $fresh;
        });
    }

    public function void(Invoice $invoice, string $reason, User $actor): Invoice
    {
        if (! $actor->isOwner()) {
            throw new DomainException('Нэхэмжлэлийг зөвхөн эзэн хүчингүй болгоно.');
        }

        if (trim($reason) === '') {
            throw new DomainException('Шалтгаан бичнэ үү.');
        }

        return Serialized::run('invoice:'.$invoice->work_order_id, function () use ($invoice, $reason, $actor): Invoice {
            $fresh = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === InvoiceStatus::Void) {
                return $fresh;
            }

            if ($fresh->paidNetMnt() > 0) {
                throw new DomainException('Төлбөр бүртгэгдсэн нэхэмжлэлийг хүчингүй болгохын өмнө төлбөрийг буцаалтаар залруулна уу.');
            }

            $fresh->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason])->save();
            Audit::record('invoice.voided', $fresh, reason: $reason, actorId: $actor->id);

            return $fresh;
        });
    }

    private function recalculate(Invoice $invoice): void
    {
        $lines = $invoice->lines()->get();

        $invoice->forceFill([
            'labor_total_mnt' => (int) $lines->where('kind', EstimateLineKind::Labor)->sum('total_mnt'),
            'parts_total_mnt' => (int) $lines->where('kind', EstimateLineKind::Part)->sum('total_mnt'),
            'discount_total_mnt' => (int) $lines->sum('discount_mnt'),
            'tax_total_mnt' => (int) $lines->sum('tax_mnt'),
            'grand_total_mnt' => (int) $lines->sum('total_mnt'),
        ])->save();
    }

    private function assertFinance(User $actor): void
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Санхүүгийн үйлдэл хийх эрхгүй байна.');
        }
    }
}
