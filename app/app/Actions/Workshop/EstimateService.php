<?php

namespace App\Actions\Workshop;

use App\Enums\EstimateLineKind;
use App\Enums\EstimateState;
use App\Enums\WorkOrderStatus;
use App\Models\EstimateRevision;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit;
use App\Support\BusinessTime;
use App\Support\DomainException;
use App\Support\EnumValue;
use App\Support\Money;
use App\Support\Serialized;

/**
 * Draft editing and publication of immutable estimate revisions. All totals are computed here;
 * posted totals are never trusted.
 */
class EstimateService
{
    public function __construct(private TransitionWorkOrder $transition) {}

    /**
     * Create a new draft revision, optionally copying lines of the latest revision.
     */
    public function createDraft(WorkOrder $order, User $actor, bool $copyLatest = true): EstimateRevision
    {
        $this->assertOffice($actor);

        if (! $order->status->isOpen()) {
            throw new DomainException('Хаагдсан ажилд үнийн санал үүсгэх боломжгүй.');
        }

        return Serialized::run('work_order:'.$order->id, function () use ($order, $actor, $copyLatest): EstimateRevision {
            $existingDraft = $order->estimateRevisions()->where('state', EstimateState::Draft)->first();

            if ($existingDraft) {
                return $existingDraft;
            }

            $latest = $order->latestRevision();

            $revision = $order->estimateRevisions()->create([
                'revision_number' => ($latest?->revision_number ?? 0) + 1,
                'state' => EstimateState::Draft,
                'diagnosis_summary' => $latest?->diagnosis_summary,
                'terms' => $latest?->terms,
                'exclusions' => $latest?->exclusions,
                'valid_until' => BusinessTime::now()->addDays(14)->toDateString(),
                'created_by' => $actor->id,
            ]);

            if ($copyLatest && $latest) {
                foreach ($latest->lines()->orderBy('sort_order')->get() as $line) {
                    $revision->lines()->create($line->only(['kind', 'description', 'quantity_milli', 'unit_price_mnt', 'discount_mnt', 'tax_mnt', 'total_mnt', 'note', 'sort_order']));
                }
            }

            return $revision;
        });
    }

    /**
     * Replace the lines of a draft revision. Each line: kind, description, quantity (string), unit_price_mnt, discount_mnt.
     *
     * @param  list<array{kind: string, description: string, quantity: string|int|float, unit_price_mnt: int, discount_mnt?: int, note?: string|null}>  $lines
     * @param  array{diagnosis_summary?: string|null, terms?: string|null, exclusions?: string|null, valid_until?: string|null}  $header
     */
    public function saveDraft(EstimateRevision $revision, array $header, array $lines, User $actor): EstimateRevision
    {
        $this->assertOffice($actor);

        return Serialized::run('work_order:'.$revision->work_order_id, function () use ($revision, $header, $lines): EstimateRevision {
            $fresh = EstimateRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();

            if ($fresh->isImmutable()) {
                throw new DomainException('Илгээсэн үнийн саналыг өөрчлөх боломжгүй. Шинэ хувилбар үүсгэнэ үү.');
            }

            $fresh->fill(collect($header)->only(['diagnosis_summary', 'terms', 'exclusions', 'valid_until'])->all())->save();
            $fresh->lines()->delete();

            foreach (array_values($lines) as $index => $line) {
                $quantity = Money::quantityToMilli($line['quantity']);
                $unit = (int) $line['unit_price_mnt'];
                $discount = (int) ($line['discount_mnt'] ?? 0);

                if (trim($line['description']) === '' || $quantity <= 0) {
                    throw new DomainException('Мөр бүрт тайлбар ба тоо хэмжээ шаардлагатай.');
                }

                $fresh->lines()->create([
                    'kind' => EstimateLineKind::from(EnumValue::of($line['kind'])),
                    'description' => trim($line['description']),
                    'quantity_milli' => $quantity,
                    'unit_price_mnt' => $unit,
                    'discount_mnt' => $discount,
                    'tax_mnt' => 0,
                    'total_mnt' => Money::lineTotal($quantity, $unit, $discount),
                    'note' => $line['note'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            $this->recalculate($fresh);

            return $fresh->refresh();
        });
    }

    /**
     * Freeze the draft, compute hash/totals, supersede an older pending revision and await approval.
     */
    public function publish(EstimateRevision $revision, User $actor): EstimateRevision
    {
        $this->assertOffice($actor);

        return Serialized::run('work_order:'.$revision->work_order_id, function () use ($revision, $actor): EstimateRevision {
            $fresh = EstimateRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $order = WorkOrder::query()->whereKey($fresh->work_order_id)->lockForUpdate()->firstOrFail();

            if ($fresh->state !== EstimateState::Draft) {
                throw new DomainException('Энэ хувилбар аль хэдийн илгээгдсэн.');
            }

            if ($fresh->lines()->count() === 0) {
                throw new DomainException('Үнийн саналд дор хаяж нэг мөр оруулна уу.');
            }

            if (! in_array($order->status, [WorkOrderStatus::Diagnosing, WorkOrderStatus::AwaitingApproval, WorkOrderStatus::Approved, WorkOrderStatus::InProgress, WorkOrderStatus::WaitingParts], true)) {
                throw new DomainException('Энэ төлөвт үнийн санал илгээх боломжгүй (эхлээд онош тавина).');
            }

            $this->recalculate($fresh);
            $fresh->refresh();

            $order->estimateRevisions()
                ->where('id', '!=', $fresh->id)
                ->where('state', EstimateState::Sent)
                ->update(['state' => EstimateState::Superseded]);

            $fresh->forceFill([
                'state' => EstimateState::Sent,
                'published_at' => now(),
                'published_by' => $actor->id,
                'content_hash' => $this->contentHash($fresh),
            ])->save();

            if ($order->status !== WorkOrderStatus::AwaitingApproval) {
                if ($order->status === WorkOrderStatus::WaitingParts) {
                    $order->forceFill(['status_before_wait' => null])->save();
                }

                if ($order->diagnosed_at === null) {
                    $order->forceFill(['diagnosed_at' => now()])->save();
                }

                $this->transition->system($order, WorkOrderStatus::AwaitingApproval, $actor, 'Үнийн санал #'.$fresh->revision_number.' илгээсэн');
            }

            Audit::record('estimate.published', $fresh, ['total' => $fresh->grand_total_mnt, 'hash' => $fresh->content_hash], actorId: $actor->id);

            return $fresh;
        });
    }

    public function contentHash(EstimateRevision $revision): string
    {
        $payload = [
            'order' => $revision->work_order_id,
            'revision' => $revision->revision_number,
            'total' => $revision->grand_total_mnt,
            'valid_until' => $revision->valid_until?->toDateString(),
            'terms' => $revision->terms,
            'exclusions' => $revision->exclusions,
            'lines' => $revision->lines()->orderBy('sort_order')->get()
                ->map(fn ($line): array => [$line->kind->value, $line->description, $line->quantity_milli, $line->unit_price_mnt, $line->discount_mnt, $line->tax_mnt, $line->total_mnt])
                ->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function recalculate(EstimateRevision $revision): void
    {
        $lines = $revision->lines()->get();

        $revision->forceFill([
            'labor_total_mnt' => (int) $lines->where('kind', EstimateLineKind::Labor)->sum('total_mnt'),
            'parts_total_mnt' => (int) $lines->where('kind', EstimateLineKind::Part)->sum('total_mnt'),
            'discount_total_mnt' => (int) $lines->sum('discount_mnt'),
            'tax_total_mnt' => (int) $lines->sum('tax_mnt'),
            'grand_total_mnt' => (int) $lines->sum('total_mnt'),
        ])->save();
    }

    private function assertOffice(User $actor): void
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Үнийн саналыг зөвхөн эзэн эсвэл үйлчилгээний зөвлөх засна.');
        }
    }
}
