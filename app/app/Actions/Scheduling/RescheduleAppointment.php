<?php

namespace App\Actions\Scheduling;

use App\Actions\Maintenance\CancelPendingNotifications;
use App\Actions\Maintenance\PlanAppointmentNotifications;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\Serialized;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Edit time/resources of a confirmed appointment. Used by the edit form, drag/drop, resize and the
 * keyboard modal alike. Optimistic version check rejects stale edits; reservations are replaced atomically.
 */
class RescheduleAppointment
{
    public function __construct(
        private ReservationGuard $guard,
        private ConfirmAppointment $confirm,
        private PlanAppointmentNotifications $notifications,
        private CancelPendingNotifications $cancelNotifications,
    ) {}

    public function handle(Appointment $appointment, CarbonImmutable $start, CarbonImmutable $end, ?int $bayId, ?int $mechanicId, int $expectedVersion, User $actor, ?int $bufferMin = null): Appointment
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Цаг өөрчлөх эрхгүй байна.');
        }

        $start = $start->utc();
        $end = $end->utc();
        $bayId = $appointment->type->value === 'workshop' ? $bayId : null;

        $this->guard->validateResources($appointment->type, $bayId, $mechanicId);
        $this->guard->validateTimes($start, $end, $bayId, $mechanicId);

        $updated = Serialized::run('scheduling', function () use ($appointment, $start, $end, $bayId, $mechanicId, $expectedVersion, $actor, $bufferMin): Appointment {
            $fresh = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($fresh->version !== $expectedVersion) {
                throw new DomainException('Энэ цагийг өөр ажилтан өөрчилсөн байна. Хуудсаа шинэчлээд дахин оролдоно уу.');
            }

            if ($fresh->status !== AppointmentStatus::Confirmed) {
                throw new DomainException('Зөвхөн баталгаажсан цагийг өөрчилнө.');
            }

            $this->guard->lockResources($bayId, $mechanicId);

            $buffer = $bufferMin ?? $fresh->buffer_min;
            [$from, $to] = $this->guard->reservationWindow($start, $end, $buffer, $fresh->travel_min);
            $currentIds = $fresh->reservations()->where('active', true)->pluck('id')->all();
            $this->guard->assertNoConflict($from, $to, $bayId, $mechanicId, $currentIds);

            $before = ['start' => $fresh->planned_start?->toIso8601String(), 'bay' => $fresh->bay_id, 'mechanic' => $fresh->mechanic_user_id];

            $fresh->reservations()->where('active', true)->update(['active' => false, 'released_at' => now(), 'release_reason' => 'rescheduled']);

            $fresh->forceFill([
                'planned_start' => $start,
                'planned_end' => $end,
                'bay_id' => $bayId,
                'mechanic_user_id' => $mechanicId,
                'buffer_min' => $buffer,
                'version' => $fresh->version + 1,
                'customer_change_request' => null,
                'customer_change_requested_at' => null,
            ])->save();

            $this->confirm->reserve($fresh, $from, $to);

            Audit::record('appointment.rescheduled', $fresh, ['before' => $before], actorId: $actor->id);

            return $fresh;
        });

        DB::afterCommit(function () use ($updated): void {
            $this->cancelNotifications->forAppointment($updated, 'rescheduled');
            $this->notifications->handle($updated);
        });

        return $updated;
    }
}
