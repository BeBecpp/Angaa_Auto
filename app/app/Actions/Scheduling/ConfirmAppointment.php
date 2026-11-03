<?php

namespace App\Actions\Scheduling;

use App\Actions\Maintenance\CancelPendingNotifications;
use App\Actions\Maintenance\PlanAppointmentNotifications;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\ResourceReservation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\EnumValue;
use App\Support\Serialized;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Staff confirmation: transactionally reserves bay/mechanic and creates a confirmed appointment.
 * When converting a public request, conversion happens exactly once (a repeat returns the same appointment).
 */
class ConfirmAppointment
{
    public function __construct(
        private ReservationGuard $guard,
        private PlanAppointmentNotifications $notifications,
        private CancelPendingNotifications $cancelNotifications,
    ) {}

    /**
     * @param  array{customer_id: int, vehicle_id?: int|null, service_id?: int|null, type?: string, start: CarbonImmutable, end: CarbonImmutable, bay_id?: int|null, mechanic_user_id?: int|null, buffer_min?: int, travel_min?: int, staff_notes?: string|null, service_request_id?: int|null}  $data
     */
    public function handle(array $data, User $actor): Appointment
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Цаг батлах эрхгүй байна.');
        }

        $type = AppointmentType::from(EnumValue::of($data['type'] ?? 'workshop'));
        $bayId = $type === AppointmentType::Workshop ? ($data['bay_id'] ?? null) : null;
        $mechanicId = $data['mechanic_user_id'] ?? null;
        $buffer = (int) ($data['buffer_min'] ?? 0);
        $travel = $type === AppointmentType::Callout ? (int) ($data['travel_min'] ?? 0) : 0;
        $start = $data['start']->utc();
        $end = $data['end']->utc();

        if ($type === AppointmentType::Callout && $travel <= 0) {
            throw new DomainException('Очиж үйлчлэхэд замын хугацааг (минут) заавал оруулна.');
        }

        $customer = Customer::query()->findOrFail($data['customer_id']);

        if (isset($data['vehicle_id'])) {
            Vehicle::query()->findOrFail($data['vehicle_id']);
        }

        $this->guard->validateResources($type, $bayId, $mechanicId);
        $this->guard->validateTimes($start, $end, $bayId, $mechanicId);

        $appointment = Serialized::run('scheduling', function () use ($data, $type, $bayId, $mechanicId, $buffer, $travel, $start, $end, $customer, $actor): Appointment {
            $request = null;

            if (! empty($data['service_request_id'])) {
                $request = ServiceRequest::query()->whereKey($data['service_request_id'])->lockForUpdate()->firstOrFail();

                if ($request->converted_appointment_id) {
                    return Appointment::query()->findOrFail($request->converted_appointment_id);
                }

                if (in_array($request->status, [RequestStatus::Closed, RequestStatus::Spam], true)) {
                    throw new DomainException('Хаагдсан хүсэлтийг цаг болгох боломжгүй.');
                }
            }

            $this->guard->lockResources($bayId, $mechanicId);
            [$from, $to] = $this->guard->reservationWindow($start, $end, $buffer, $travel);
            $this->guard->assertNoConflict($from, $to, $bayId, $mechanicId);

            $appointment = Appointment::query()->create([
                'service_request_id' => $request?->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'service_id' => $data['service_id'] ?? $request?->service_id,
                'type' => $type,
                'status' => AppointmentStatus::Confirmed,
                'planned_start' => $start,
                'planned_end' => $end,
                'buffer_min' => $buffer,
                'travel_min' => $travel,
                'bay_id' => $bayId,
                'mechanic_user_id' => $mechanicId,
                'staff_notes' => $data['staff_notes'] ?? null,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
            ]);

            $this->reserve($appointment, $from, $to);

            if ($request) {
                $request->forceFill([
                    'status' => RequestStatus::Converted,
                    'converted_appointment_id' => $appointment->id,
                    'converted_at' => now(),
                    'customer_id' => $customer->id,
                    'vehicle_id' => $data['vehicle_id'] ?? null,
                    'first_responded_at' => $request->first_responded_at ?? now(),
                ])->save();
            }

            Audit::record('appointment.confirmed', $appointment, ['request_id' => $request?->id], actorId: $actor->id);

            return $appointment;
        });

        DB::afterCommit(function () use ($appointment): void {
            $this->notifications->handle($appointment->fresh());
            $this->cancelNotifications->forRebooking($appointment->customer_id, $appointment->vehicle_id);
        });

        return $appointment;
    }

    public function reserve(Appointment $appointment, CarbonImmutable $from, CarbonImmutable $to): void
    {
        if ($appointment->bay_id) {
            ResourceReservation::query()->create([
                'appointment_id' => $appointment->id,
                'resource_type' => 'bay',
                'bay_id' => $appointment->bay_id,
                'starts_at' => $from,
                'ends_at' => $to,
                'active' => true,
            ]);
        }

        if ($appointment->mechanic_user_id) {
            ResourceReservation::query()->create([
                'appointment_id' => $appointment->id,
                'resource_type' => 'mechanic',
                'mechanic_user_id' => $appointment->mechanic_user_id,
                'starts_at' => $from,
                'ends_at' => $to,
                'active' => true,
            ]);
        }
    }
}
