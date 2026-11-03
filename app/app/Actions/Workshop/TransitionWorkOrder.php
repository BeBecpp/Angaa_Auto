<?php

namespace App\Actions\Workshop;

use App\Enums\StaffRole;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatusEvent;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\Serialized;

/**
 * The only path that changes work_orders.status. Enforces the explicit transition graph, role rules,
 * approval-before-work, and appends a status event. No numeric ordering shortcuts.
 */
class TransitionWorkOrder
{
    /**
     * Transitions a mechanic may perform on an order assigned to them.
     *
     * @var list<array{0: WorkOrderStatus, 1: WorkOrderStatus}>
     */
    private const MECHANIC_ALLOWED = [
        [WorkOrderStatus::Received, WorkOrderStatus::Diagnosing],
        [WorkOrderStatus::Approved, WorkOrderStatus::InProgress],
        [WorkOrderStatus::InProgress, WorkOrderStatus::QualityCheck],
        [WorkOrderStatus::InProgress, WorkOrderStatus::WaitingParts],
        [WorkOrderStatus::WaitingParts, WorkOrderStatus::InProgress],
    ];

    public function handle(WorkOrder $order, WorkOrderStatus $target, User $actor, ?string $reason = null): WorkOrder
    {
        return Serialized::run('work_order:'.$order->id, function () use ($order, $target, $actor, $reason): WorkOrder {
            $fresh = WorkOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $fresh->status;

            if ($from === $target) {
                return $fresh;
            }

            $this->assertAllowed($fresh, $from, $target, $actor, $reason);

            $updates = ['status' => $target, 'version' => $fresh->version + 1];

            if ($target === WorkOrderStatus::WaitingParts) {
                $updates['status_before_wait'] = $from;
            }

            if ($from === WorkOrderStatus::WaitingParts) {
                $updates['status_before_wait'] = null;
            }

            if ($target === WorkOrderStatus::AwaitingApproval && $fresh->diagnosed_at === null) {
                $updates['diagnosed_at'] = now();
            }

            if ($target === WorkOrderStatus::ReadyForPickup) {
                $updates['ready_at'] = now();
            }

            if ($target === WorkOrderStatus::Cancelled) {
                $updates['cancellation_reason'] = $reason;
                $fresh->reservations()->where('active', true)->update(['active' => false, 'released_at' => now(), 'release_reason' => 'order_cancelled']);
                $fresh->reservations()->whereNotNull('actual_occupied_from')->whereNull('actual_occupied_to')->update(['actual_occupied_to' => now()]);
            }

            $fresh->forceFill($updates)->save();

            WorkOrderStatusEvent::query()->create([
                'work_order_id' => $fresh->id,
                'from_status' => $from,
                'to_status' => $target,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            Audit::record('work_order.status', $fresh, ['from' => $from->value, 'to' => $target->value], $reason, $actor->id);

            return $fresh;
        });
    }

    private function assertAllowed(WorkOrder $order, WorkOrderStatus $from, WorkOrderStatus $target, User $actor, ?string $reason): void
    {
        if (! $actor->isStaff() || ! $actor->active) {
            throw new DomainException('Эрхгүй үйлдэл.');
        }

        $graphAllows = $from === WorkOrderStatus::WaitingParts
            ? in_array($target, [$order->status_before_wait ?? WorkOrderStatus::InProgress, WorkOrderStatus::Cancelled], true)
            : $from->canTransitionTo($target);

        if (! $graphAllows) {
            throw new DomainException("«{$from->getLabel()}» төлвөөс «{$target->getLabel()}» руу шилжих боломжгүй.");
        }

        if ($actor->hasRole(StaffRole::Mechanic)) {
            $mechanicMay = $order->assigned_mechanic_id === $actor->id
                && collect(self::MECHANIC_ALLOWED)->contains(fn (array $pair): bool => $pair[0] === $from && $pair[1] === $target);

            if (! $mechanicMay) {
                throw new DomainException('Механик энэ төлвийг өөрчлөх эрхгүй (зөвлөх/эзэн хийнэ).');
            }
        }

        if (in_array($target, [WorkOrderStatus::Cancelled, WorkOrderStatus::WaitingParts], true) && trim((string) $reason) === '') {
            throw new DomainException('Шалтгаан бичнэ үү.');
        }

        if ($from === WorkOrderStatus::ReadyForPickup && $target === WorkOrderStatus::InProgress && trim((string) $reason) === '') {
            throw new DomainException('Дахин засварт буцаах шалтгааныг бичнэ үү.');
        }

        if ($target === WorkOrderStatus::InProgress && $from !== WorkOrderStatus::WaitingParts && $from !== WorkOrderStatus::QualityCheck && $from !== WorkOrderStatus::ReadyForPickup) {
            $approved = $order->current_approved_estimate_revision_id !== null;
            $overridden = $order->owner_override_at !== null;

            if (! $approved && ! $overridden) {
                throw new DomainException('Хэрэглэгч үнийн саналыг зөвшөөрөөгүй тул засвар эхлүүлэх боломжгүй.');
            }
        }

        if ($target === WorkOrderStatus::Delivered) {
            throw new DomainException('Олголтыг «Машин олгох» үйлдлээр хийнэ.');
        }

        if ($target === WorkOrderStatus::Approved) {
            throw new DomainException('Зөвшөөрлийг хэрэглэгчийн шийдвэрээр бүртгэнэ.');
        }

        if ($target === WorkOrderStatus::AwaitingApproval && $from !== WorkOrderStatus::Diagnosing && $from !== WorkOrderStatus::InProgress) {
            throw new DomainException('Үнийн санал илгээхэд л зөвшөөрөл хүлээх төлөвт орно.');
        }
    }

    /**
     * Internal transition used by approval/estimate/delivery actions that already enforce their own rules.
     */
    public function system(WorkOrder $order, WorkOrderStatus $target, ?User $actor, ?string $reason = null): WorkOrder
    {
        $from = $order->status;

        if ($from === $target) {
            return $order;
        }

        $order->forceFill(['status' => $target, 'version' => $order->version + 1])->save();

        WorkOrderStatusEvent::query()->create([
            'work_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $target,
            'actor_id' => $actor?->id,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);

        return $order;
    }
}
