<?php

namespace App\Actions\Scheduling;

use App\Actions\Maintenance\CancelPendingNotifications;
use App\Actions\Vehicles\RecordOdometerReading;
use App\Enums\AppointmentStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\ResourceReservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatusEvent;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\Phone;
use App\Support\Sequence;
use App\Support\Serialized;
use Carbon\CarbonImmutable;

/**
 * Vehicle intake. Creates the work order with immutable customer/vehicle snapshots, records km and
 * marks the bay as actually occupied. Works for confirmed appointments and walk-ins.
 */
class CheckInVehicle
{
    public function __construct(
        private ReservationGuard $guard,
        private ConfirmAppointment $confirm,
        private RecordOdometerReading $odometer,
        private CancelPendingNotifications $cancelNotifications,
    ) {}

    /**
     * @param  array{complaint: string, intake_km?: int|null, fuel_level?: string|null, belongings_note?: string|null, visible_damage_note?: string|null, promised_ready_at?: CarbonImmutable|null, km_correction_note?: string|null}  $intake
     */
    public function fromAppointment(Appointment $appointment, array $intake, User $actor): WorkOrder
    {
        if (! $actor->isOfficeStaff() && ! ($actor->isMechanic() && $appointment->mechanic_user_id === $actor->id)) {
            throw new DomainException('Энэ машиныг хүлээн авах эрхгүй байна.');
        }

        if (! $appointment->vehicle_id) {
            throw new DomainException('Хүлээн авахаас өмнө цагт машин холбоно уу.');
        }

        $order = Serialized::run('scheduling', function () use ($appointment, $intake, $actor): WorkOrder {
            $fresh = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === AppointmentStatus::Arrived && $fresh->workOrder) {
                return $fresh->workOrder;
            }

            if ($fresh->status !== AppointmentStatus::Confirmed) {
                throw new DomainException('Зөвхөн баталгаажсан цагийн машиныг хүлээн авна.');
            }

            if ($fresh->bay_id) {
                $this->guard->lockResources($fresh->bay_id, null);
                $this->assertBayFree($fresh->bay_id, $fresh->reservations()->pluck('id')->all());
            }

            $order = $this->createOrder($fresh->customer, $fresh->vehicle, $intake, $actor, [
                'appointment_id' => $fresh->id,
                'service_request_id' => $fresh->service_request_id,
                'assigned_mechanic_id' => $fresh->mechanic_user_id,
                'bay_id' => $fresh->bay_id,
            ]);

            $fresh->reservations()->where('active', true)->update(['work_order_id' => $order->id]);
            $fresh->reservations()->where('active', true)->where('resource_type', 'bay')->update(['actual_occupied_from' => now()]);

            $fresh->forceFill([
                'status' => AppointmentStatus::Arrived,
                'arrived_at' => now(),
                'version' => $fresh->version + 1,
            ])->save();

            return $order;
        });

        $this->cancelNotifications->forAppointment($appointment, 'arrived');

        return $order;
    }

    /**
     * Walk-in: reserves the bay/mechanic from now for the expected duration through the same guard.
     *
     * @param  array{complaint: string, intake_km?: int|null, fuel_level?: string|null, belongings_note?: string|null, visible_damage_note?: string|null, promised_ready_at?: CarbonImmutable|null, km_correction_note?: string|null, bay_id?: int|null, mechanic_user_id?: int|null, duration_min?: int}  $intake
     */
    public function walkIn(Customer $customer, Vehicle $vehicle, array $intake, User $actor): WorkOrder
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Шууд ирсэн машиныг зөвхөн эзэн/зөвлөх бүртгэнэ.');
        }

        $bayId = $intake['bay_id'] ?? null;
        $mechanicId = $intake['mechanic_user_id'] ?? null;

        return Serialized::run('scheduling', function () use ($customer, $vehicle, $intake, $actor, $bayId, $mechanicId): WorkOrder {
            $this->guard->lockResources($bayId, $mechanicId);

            $from = CarbonImmutable::now();
            $to = $from->addMinutes(max(15, (int) ($intake['duration_min'] ?? config('angaa.scheduling.default_duration_min'))));

            if ($bayId !== null || $mechanicId !== null) {
                $this->guard->assertNoConflict($from, $to, $bayId, $mechanicId);
            }

            $order = $this->createOrder($customer, $vehicle, $intake, $actor, [
                'assigned_mechanic_id' => $mechanicId,
                'bay_id' => $bayId,
            ]);

            if ($bayId !== null) {
                ResourceReservation::query()->create([
                    'work_order_id' => $order->id,
                    'resource_type' => 'bay',
                    'bay_id' => $bayId,
                    'starts_at' => $from,
                    'ends_at' => $to,
                    'active' => true,
                    'actual_occupied_from' => $from,
                ]);
            }

            if ($mechanicId !== null) {
                ResourceReservation::query()->create([
                    'work_order_id' => $order->id,
                    'resource_type' => 'mechanic',
                    'mechanic_user_id' => $mechanicId,
                    'starts_at' => $from,
                    'ends_at' => $to,
                    'active' => true,
                ]);
            }

            return $order;
        });
    }

    /**
     * @param  list<int>  $ownReservationIds
     */
    private function assertBayFree(int $bayId, array $ownReservationIds): void
    {
        $occupied = ResourceReservation::query()
            ->where('bay_id', $bayId)
            ->whereNotIn('id', $ownReservationIds)
            ->whereNotNull('actual_occupied_from')
            ->whereNull('actual_occupied_to')
            ->exists();

        if ($occupied) {
            throw new SchedulingConflict('Энэ пост дээр өмнөх машин засварт байна (хугацаа хэтэрсэн). Өөр пост руу шилжүүлэх эсвэл өмнөх ажлыг дуусгана уу.');
        }
    }

    /**
     * @param  array<string, mixed>  $intake
     * @param  array<string, mixed>  $links
     */
    private function createOrder(Customer $customer, Vehicle $vehicle, array $intake, User $actor, array $links): WorkOrder
    {
        if (trim((string) ($intake['complaint'] ?? '')) === '') {
            throw new DomainException('Хэрэглэгчийн гомдол / хүсэлтийг бичнэ үү.');
        }

        $order = WorkOrder::query()->create($links + [
            'order_number' => Sequence::formatted('WO'),
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => WorkOrderStatus::Received,
            'complaint' => trim($intake['complaint']),
            'intake_km' => $intake['intake_km'] ?? null,
            'fuel_level' => $intake['fuel_level'] ?? null,
            'belongings_note' => $intake['belongings_note'] ?? null,
            'visible_damage_note' => $intake['visible_damage_note'] ?? null,
            'promised_ready_at' => $intake['promised_ready_at'] ?? null,
            'customer_snapshot' => [
                'display_name' => $customer->display_name,
                'phone' => Phone::mask($customer->normalized_contact_phone),
            ],
            'vehicle_snapshot' => [
                'plate' => $vehicle->current_plate_display,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
            ],
            'received_by' => $actor->id,
            'received_at' => now(),
            'is_demo' => $customer->is_demo,
        ]);

        if (isset($intake['intake_km'])) {
            $this->odometer->handle($vehicle, (int) $intake['intake_km'], $actor, now(), $intake['km_correction_note'] ?? null, $order->id);
        }

        WorkOrderStatusEvent::query()->create([
            'work_order_id' => $order->id,
            'from_status' => null,
            'to_status' => WorkOrderStatus::Received,
            'actor_id' => $actor->id,
            'occurred_at' => now(),
        ]);

        Audit::record('work_order.received', $order, actorId: $actor->id);

        return $order;
    }
}
