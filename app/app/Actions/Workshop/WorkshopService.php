<?php

namespace App\Actions\Workshop;

use App\Enums\AppointmentStatus;
use App\Enums\InspectionCondition;
use App\Enums\InvoiceStatus;
use App\Enums\MediaCategory;
use App\Enums\PlanStatus;
use App\Enums\QcResult;
use App\Enums\WorkOrderStatus;
use App\Models\InspectionItem;
use App\Models\MediaFile;
use App\Models\QcCheck;
use App\Models\RepairItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Media\ImageProcessor;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\EnumValue;
use App\Support\Serialized;
use Illuminate\Http\UploadedFile;

/**
 * Inspection, photos, repair log, owner override, QC and delivery.
 */
class WorkshopService
{
    public function __construct(private TransitionWorkOrder $transition, private ImageProcessor $images) {}

    /**
     * @param  array{label: string, condition: string, finding_internal?: string|null, finding_customer?: string|null}  $data
     */
    public function saveInspectionItem(WorkOrder $order, array $data, User $actor, ?InspectionItem $item = null): InspectionItem
    {
        $this->assertCanWork($order, $actor);

        if (! in_array($order->status, [WorkOrderStatus::Received, WorkOrderStatus::Diagnosing, WorkOrderStatus::InProgress, WorkOrderStatus::AwaitingApproval], true)) {
            throw new DomainException('Энэ төлөвт үзлэгийн тэмдэглэл өөрчлөх боломжгүй.');
        }

        if ($item && $item->published_at && $data['finding_customer'] !== $item->finding_customer) {
            throw new DomainException('Хэрэглэгчид нийтэлсэн дүгнэлтийг өөрчлөх боломжгүй. Шинэ мөр нэмнэ үү.');
        }

        $attributes = [
            'label' => trim($data['label']),
            'condition' => InspectionCondition::from(EnumValue::of($data['condition'])),
            'finding_internal' => $data['finding_internal'] ?? null,
            'finding_customer' => $data['finding_customer'] ?? null,
            'checked_by' => $actor->id,
            'checked_at' => now(),
        ];

        if ($item) {
            $item->fill($attributes)->save();

            return $item;
        }

        return $order->inspectionItems()->create($attributes + ['sort_order' => (int) $order->inspectionItems()->max('sort_order') + 1]);
    }

    /**
     * Office staff publish customer-facing findings and choose which photos the customer may see.
     *
     * @param  list<int>  $itemIds
     * @param  list<int>  $mediaIds
     */
    public function publishInspection(WorkOrder $order, array $itemIds, array $mediaIds, User $actor): int
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Хэрэглэгчид харагдах дүгнэлтийг зөвлөх/эзэн нийтэлнэ.');
        }

        $published = $order->inspectionItems()->whereIn('id', $itemIds)->whereNull('published_at')->whereNotNull('finding_customer')->update(['published_at' => now()]);
        $order->media()->whereIn('id', $mediaIds)->update(['customer_visible' => true]);

        Audit::record('inspection.published', $order, ['items' => $itemIds, 'media' => $mediaIds], actorId: $actor->id);

        return $published;
    }

    public function attachPhoto(WorkOrder $order, UploadedFile $file, MediaCategory $category, User $actor, ?string $caption = null, ?int $inspectionItemId = null): MediaFile
    {
        $this->assertCanWork($order, $actor);

        if ($order->status === WorkOrderStatus::Delivered || $order->status === WorkOrderStatus::Cancelled) {
            throw new DomainException('Хаагдсан ажилд зураг нэмэх боломжгүй.');
        }

        if ($inspectionItemId !== null && ! $order->inspectionItems()->whereKey($inspectionItemId)->exists()) {
            throw new DomainException('Үзлэгийн мөр олдсонгүй.');
        }

        $stored = $this->images->store($file);

        return MediaFile::query()->create($stored + [
            'uploaded_by' => $actor->id,
            'work_order_id' => $order->id,
            'inspection_item_id' => $inspectionItemId,
            'category' => $category,
            'caption' => $caption,
            'customer_visible' => false,
        ]);
    }

    /**
     * Repair log entry. Work must be inside approved scope (linked to an approved estimate line)
     * unless the owner recorded an override.
     *
     * @param  array{description: string, work_done?: string|null, estimate_line_id?: int|null, quantity_milli?: int, cost_mnt?: int|null, is_part?: bool}  $data
     */
    public function recordRepair(WorkOrder $order, array $data, User $actor): RepairItem
    {
        $this->assertCanWork($order, $actor);

        if (! $order->status->allowsRepairWork()) {
            throw new DomainException('Засвар зөвшөөрөгдсөн/явагдаж буй үед л бүртгэнэ.');
        }

        $lineId = $data['estimate_line_id'] ?? null;

        if ($lineId !== null) {
            $inScope = $order->currentApprovedRevision?->lines()->whereKey($lineId)->exists() ?? false;

            if (! $inScope) {
                throw new DomainException('Энэ мөр зөвшөөрсөн үнийн саналд байхгүй.');
            }
        } elseif ($order->owner_override_at === null) {
            throw new DomainException('Зөвшөөрсөн хүрээнээс гадуурх ажлыг шинэ үнийн саналаар зөвшөөрүүлнэ.');
        }

        if (isset($data['cost_mnt']) && ! $actor->isOfficeStaff()) {
            $data['cost_mnt'] = null;
        }

        $item = $order->repairItems()->create([
            'estimate_line_id' => $lineId,
            'description' => trim($data['description']),
            'work_done' => $data['work_done'] ?? null,
            'quantity_milli' => $data['quantity_milli'] ?? 1000,
            'cost_mnt' => $data['cost_mnt'] ?? null,
            'is_part' => (bool) ($data['is_part'] ?? false),
            'completed_by' => $actor->id,
            'completed_at' => now(),
        ]);

        if ($order->status === WorkOrderStatus::Approved) {
            $this->transition->handle($order, WorkOrderStatus::InProgress, $actor);
        }

        return $item;
    }

    /**
     * Owner-only, reasoned override that allows work before customer approval. Audited separately from consent.
     */
    public function ownerOverride(WorkOrder $order, string $reason, User $actor): WorkOrder
    {
        if (! $actor->isOwner()) {
            throw new DomainException('Зөвхөн эзэн онцгой зөвшөөрөл өгнө.');
        }

        if (trim($reason) === '') {
            throw new DomainException('Онцгой зөвшөөрлийн шалтгааныг бичнэ үү.');
        }

        $order->forceFill(['owner_override_by' => $actor->id, 'owner_override_at' => now(), 'owner_override_reason' => $reason])->save();
        Audit::record('work_order.owner_override', $order, reason: $reason, actorId: $actor->id);

        if (in_array($order->status, [WorkOrderStatus::Diagnosing, WorkOrderStatus::AwaitingApproval], true)) {
            $this->transition->system($order, WorkOrderStatus::Approved, $actor, 'Эзний онцгой зөвшөөрөл: '.$reason);
        }

        return $order;
    }

    /**
     * @param  list<array{item: string, result: string, note?: string|null}>  $checks
     */
    public function completeQualityCheck(WorkOrder $order, array $checks, User $actor, ?string $customerSummary = null): WorkOrder
    {
        if (! $actor->isOfficeStaff() && ! ($actor->isMechanic() && $actor->staffProfile?->capabilities && in_array('qc', $actor->staffProfile->capabilities, true))) {
            throw new DomainException('Чанарын шалгалтыг эрх бүхий ажилтан хийнэ.');
        }

        if ($checks === []) {
            throw new DomainException('Шалгалтын жагсаалт хоосон байна.');
        }

        return Serialized::run('work_order:'.$order->id, function () use ($order, $checks, $actor, $customerSummary): WorkOrder {
            $fresh = WorkOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status !== WorkOrderStatus::QualityCheck) {
                throw new DomainException('Ажил чанарын шалгалтын төлөвт ороогүй байна.');
            }

            $failed = false;

            foreach ($checks as $check) {
                $result = QcResult::from(EnumValue::of($check['result']));
                $failed = $failed || $result === QcResult::Fail;

                QcCheck::query()->create([
                    'work_order_id' => $fresh->id,
                    'item' => $check['item'],
                    'result' => $result,
                    'note' => $check['note'] ?? null,
                    'checked_by' => $actor->id,
                    'checked_at' => now(),
                ]);
            }

            if ($customerSummary !== null) {
                $fresh->forceFill(['customer_summary' => $customerSummary])->save();
            }

            $target = $failed ? WorkOrderStatus::InProgress : WorkOrderStatus::ReadyForPickup;
            $this->transition->system($fresh, $target, $actor, $failed ? 'Чанарын шалгалтад тэнцээгүй' : 'Чанарын шалгалт тэнцсэн');

            if (! $failed) {
                $fresh->forceFill(['ready_at' => now()])->save();
            }

            Audit::record('work_order.qc', $fresh, ['passed' => ! $failed, 'items' => count($checks)], actorId: $actor->id);

            return $fresh;
        });
    }

    /**
     * Hand the vehicle back. An unpaid balance requires the owner's credit reason; payment state stays truthful.
     */
    public function deliver(WorkOrder $order, User $actor, ?string $unpaidReason = null): WorkOrder
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Машиныг зөвхөн эзэн/зөвлөх олгоно.');
        }

        return Serialized::run('work_order:'.$order->id, function () use ($order, $actor, $unpaidReason): WorkOrder {
            $fresh = WorkOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status !== WorkOrderStatus::ReadyForPickup) {
                throw new DomainException('Зөвхөн «Авахад бэлэн» ажлыг олгоно.');
            }

            $invoice = $fresh->invoices()->where('status', InvoiceStatus::Issued)->latest('id')->first();
            $balance = $invoice?->balanceMnt();

            if ($invoice === null || $balance > 0) {
                if (! $actor->isOwner() || trim((string) $unpaidReason) === '') {
                    throw new DomainException($invoice === null
                        ? 'Нэхэмжлэл гаргаагүй байна. Эзэн шалтгаан бичиж зээлээр олгож болно.'
                        : 'Төлбөрийн үлдэгдэлтэй. Эзэн шалтгаан бичиж зээлээр олгож болно.');
                }

                $fresh->forceFill(['unpaid_delivery_reason' => $unpaidReason])->save();
                Audit::record('work_order.unpaid_delivery', $fresh, ['balance' => $balance], $unpaidReason, $actor->id);
            }

            $fresh->forceFill(['delivered_at' => now(), 'delivered_by' => $actor->id])->save();
            $this->transition->system($fresh, WorkOrderStatus::Delivered, $actor, 'Машин олгосон');

            $fresh->reservations()->whereNotNull('actual_occupied_from')->whereNull('actual_occupied_to')->update(['actual_occupied_to' => now()]);
            $fresh->reservations()->where('active', true)->update(['active' => false, 'released_at' => now(), 'release_reason' => 'delivered']);

            if ($fresh->appointment) {
                $fresh->appointment->forceFill(['status' => AppointmentStatus::Fulfilled])->save();
            }

            Audit::record('work_order.delivered', $fresh, actorId: $actor->id);

            return $fresh;
        });
    }

    /**
     * Mark an explicitly matched maintenance plan as satisfied by this order. Pending reminders get cancelled by the caller.
     */
    public function satisfiesPlan(WorkOrder $order, int $planId, User $actor): void
    {
        $plan = $order->vehicle->maintenancePlans()->whereKey($planId)->where('status', PlanStatus::Active)->firstOrFail();
        $plan->forceFill(['status' => PlanStatus::Satisfied, 'satisfied_by_work_order_id' => $order->id, 'version' => $plan->version + 1])->save();
        Audit::record('maintenance_plan.satisfied', $plan, ['work_order' => $order->id], actorId: $actor->id);
    }

    private function assertCanWork(WorkOrder $order, User $actor): void
    {
        if ($actor->isOfficeStaff()) {
            return;
        }

        if ($actor->isMechanic() && $actor->active && $order->assigned_mechanic_id === $actor->id) {
            return;
        }

        throw new DomainException('Энэ ажлыг засах эрхгүй байна.');
    }
}
