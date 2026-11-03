<?php

namespace App\Actions\Maintenance;

use App\Enums\PlanStatus;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit;
use App\Support\DomainException;

/**
 * Per-service, per-vehicle next-service plans chosen by staff. There is no universal default interval.
 */
class MaintenancePlanService
{
    public function __construct(private CancelPendingNotifications $cancel) {}

    /**
     * @param  array{description: string, due_date?: string|null, due_km?: int|null, service_id?: int|null, recommendation_source_note?: string|null, supersedes_id?: int|null}  $data
     */
    public function createFromWorkOrder(WorkOrder $order, array $data, User $actor): MaintenancePlan
    {
        if (! $actor->isStaff()) {
            throw new DomainException('Эрхгүй үйлдэл.');
        }

        $dueDate = $data['due_date'] ?? null;
        $dueKm = isset($data['due_km']) && $data['due_km'] !== '' ? (int) $data['due_km'] : null;

        if ($dueDate === null && $dueKm === null) {
            throw new DomainException('Дараагийн үйлчилгээний огноо эсвэл км-ийн аль нэгийг заавал оруулна.');
        }

        if (trim($data['description']) === '') {
            throw new DomainException('Ямар үйлчилгээ болохыг бичнэ үү.');
        }

        $reading = $order->vehicle->latestVerifiedReading();

        if ($dueKm !== null && $reading !== null && $dueKm <= $reading->km) {
            throw new DomainException("Дараагийн км одоогийн ({$reading->km} км)-ээс их байх ёстой.");
        }

        $supersedes = null;

        if (! empty($data['supersedes_id'])) {
            $supersedes = MaintenancePlan::query()->where('vehicle_id', $order->vehicle_id)->whereKey($data['supersedes_id'])->first();
        }

        $plan = MaintenancePlan::query()->create([
            'customer_id' => $order->customer_id,
            'vehicle_id' => $order->vehicle_id,
            'source_work_order_id' => $order->id,
            'service_id' => $data['service_id'] ?? null,
            'description' => trim($data['description']),
            'due_date' => $dueDate,
            'due_km' => $dueKm,
            'based_on_reading_id' => $reading?->id,
            'recommendation_source_note' => $data['recommendation_source_note'] ?? null,
            'status' => PlanStatus::Active,
            'supersedes_id' => $supersedes?->id,
            'created_by' => $actor->id,
            'is_demo' => $order->is_demo,
        ]);

        if ($supersedes && $supersedes->status === PlanStatus::Active) {
            $this->changeStatus($supersedes, PlanStatus::Satisfied, $actor, 'Шинэ төлөвлөгөөгөөр солигдсон');
        }

        Audit::record('maintenance_plan.created', $plan, ['due_date' => $dueDate, 'due_km' => $dueKm], actorId: $actor->id);

        return $plan;
    }

    public function changeStatus(MaintenancePlan $plan, PlanStatus $status, User $actor, ?string $reason = null): MaintenancePlan
    {
        if (! $actor->isStaff()) {
            throw new DomainException('Эрхгүй үйлдэл.');
        }

        $plan->forceFill(['status' => $status, 'version' => $plan->version + 1])->save();

        if ($status !== PlanStatus::Active) {
            $this->cancel->forPlan($plan, 'plan_'.$status->value);
        }

        Audit::record('maintenance_plan.status', $plan, ['status' => $status->value], $reason, $actor->id);

        return $plan;
    }
}
