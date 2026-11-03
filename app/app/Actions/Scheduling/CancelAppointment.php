<?php

namespace App\Actions\Scheduling;

use App\Actions\Maintenance\CancelPendingNotifications;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\Serialized;

/**
 * Cancel or mark no-show. The appointment stays on the calendar as history; reservations are released
 * and pending reminders invalidated.
 */
class CancelAppointment
{
    public function __construct(private CancelPendingNotifications $cancelNotifications) {}

    public function handle(Appointment $appointment, string $reason, User $actor, bool $noShow = false): Appointment
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Цаг цуцлах эрхгүй байна.');
        }

        if (trim($reason) === '') {
            throw new DomainException('Шалтгаан бичнэ үү.');
        }

        $updated = Serialized::run('scheduling', function () use ($appointment, $reason, $actor, $noShow): Appointment {
            $fresh = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            $allowed = $noShow
                ? [AppointmentStatus::Confirmed]
                : [AppointmentStatus::Requested, AppointmentStatus::Confirmed];

            if (! in_array($fresh->status, $allowed, true)) {
                throw new DomainException('Энэ төлөвтэй цагийг '.($noShow ? 'ирээгүй гэж тэмдэглэх' : 'цуцлах').' боломжгүй.');
            }

            $fresh->reservations()->where('active', true)->update([
                'active' => false,
                'released_at' => now(),
                'release_reason' => $noShow ? 'no_show' : 'cancelled',
            ]);

            $fresh->forceFill([
                'status' => $noShow ? AppointmentStatus::NoShow : AppointmentStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'version' => $fresh->version + 1,
            ])->save();

            Audit::record($noShow ? 'appointment.no_show' : 'appointment.cancelled', $fresh, reason: $reason, actorId: $actor->id);

            return $fresh;
        });

        $this->cancelNotifications->forAppointment($updated, $noShow ? 'no_show' : 'cancelled');

        return $updated;
    }
}
