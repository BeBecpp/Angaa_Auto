<?php

namespace App\Actions\Maintenance;

use App\Enums\OutboxStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\MaintenancePlan;
use App\Models\NotificationOutbox;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cancels messages that have not been handed to a transport yet. Messages already dispatching keep
 * their state and are re-checked by the dispatcher before sending.
 */
class CancelPendingNotifications
{
    private const PENDING = [OutboxStatus::Scheduled, OutboxStatus::Queued];

    public function forAppointment(Appointment $appointment, string $reason): int
    {
        return $this->cancel(
            NotificationOutbox::query()->where('aggregate_type', 'appointment')->where('aggregate_id', $appointment->id),
            $reason,
        );
    }

    public function forPlan(MaintenancePlan $plan, string $reason): int
    {
        return $this->cancel(
            NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan')->where('aggregate_id', $plan->id),
            $reason,
        );
    }

    /**
     * Rebooking: the customer already has a visit for this vehicle, so pending "service due" nudges stop.
     */
    public function forRebooking(int $customerId, ?int $vehicleId): int
    {
        if ($vehicleId === null) {
            return 0;
        }

        $planIds = MaintenancePlan::query()->where('customer_id', $customerId)->where('vehicle_id', $vehicleId)->pluck('id');

        return $this->cancel(
            NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan')->whereIn('aggregate_id', $planIds),
            'rebooked',
        );
    }

    public function forCustomer(Customer $customer, string $reason): int
    {
        return $this->cancel(NotificationOutbox::query()->where('customer_id', $customer->id), $reason);
    }

    /**
     * @param  Builder<NotificationOutbox>  $query
     */
    private function cancel($query, string $reason): int
    {
        return $query->whereIn('status', self::PENDING)->update([
            'status' => OutboxStatus::Cancelled,
            'skip_reason' => $reason,
            'updated_at' => now(),
        ]);
    }
}
