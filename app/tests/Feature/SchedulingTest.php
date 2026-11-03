<?php

namespace Tests\Feature;

use App\Actions\Scheduling\CancelAppointment;
use App\Actions\Scheduling\CheckInVehicle;
use App\Actions\Scheduling\ConfirmAppointment;
use App\Actions\Scheduling\RescheduleAppointment;
use App\Actions\Scheduling\SchedulingConflict;
use App\Enums\AppointmentStatus;
use App\Filament\Pages\ScheduleCalendar;
use App\Models\Closure;
use App\Models\ResourceReservation;
use App\Models\ServiceRequest;
use App\Models\ShopBay;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkshop;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use BuildsWorkshop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 08:00'));
        $this->setUpWorkshop();
    }

    public function test_adjacent_slots_are_allowed_and_overlaps_on_bay_or_mechanic_are_denied(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();

        $this->appointmentFor($customer, $vehicle, '2026-11-05 10:00');
        $second = $this->appointmentFor($customer, $vehicle, '2026-11-05 11:00');
        $this->assertSame(AppointmentStatus::Confirmed, $second->status);

        $otherBay = ShopBay::factory()->create();
        $this->expectException(SchedulingConflict::class);

        app(ConfirmAppointment::class)->handle([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start' => $this->localTime('2026-11-05 10:30'),
            'end' => $this->localTime('2026-11-05 11:30'),
            'bay_id' => $otherBay->id,
            'mechanic_user_id' => $this->mechanic->id,
        ], $this->advisor);
    }

    public function test_same_bay_with_a_different_mechanic_is_denied(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $this->appointmentFor($customer, $vehicle, '2026-11-05 10:00');
        $otherMechanic = User::factory()->mechanic()->create();

        $this->expectException(SchedulingConflict::class);
        app(ConfirmAppointment::class)->handle([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
            'start' => $this->localTime('2026-11-05 10:15'), 'end' => $this->localTime('2026-11-05 10:45'),
            'bay_id' => $this->bay->id, 'mechanic_user_id' => $otherMechanic->id,
        ], $this->advisor);
    }

    public function test_buffer_blocks_the_directly_adjacent_slot(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        app(ConfirmAppointment::class)->handle([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
            'start' => $this->localTime('2026-11-05 10:00'), 'end' => $this->localTime('2026-11-05 11:00'),
            'bay_id' => $this->bay->id, 'mechanic_user_id' => $this->mechanic->id, 'buffer_min' => 15,
        ], $this->advisor);

        $this->expectException(SchedulingConflict::class);
        $this->appointmentFor($customer, $vehicle, '2026-11-05 11:00');
    }

    public function test_opening_hours_and_closures_are_enforced(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();

        try {
            $this->appointmentFor($customer, $vehicle, '2026-11-05 17:30');
            $this->fail('Outside opening hours must be rejected');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('ажлын цаг', $exception->getMessage());
        }

        Closure::query()->create(['scope' => 'mechanic', 'scope_id' => $this->mechanic->id, 'starts_at' => $this->localTime('2026-11-06 00:00'), 'ends_at' => $this->localTime('2026-11-07 00:00'), 'reason' => 'Чөлөө']);

        $this->expectExceptionMessage('Чөлөө');
        $this->appointmentFor($customer, $vehicle, '2026-11-06 10:00');
    }

    public function test_cancellation_releases_the_reservation_and_keeps_history(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $first = $this->appointmentFor($customer, $vehicle, '2026-11-05 10:00');

        app(CancelAppointment::class)->handle($first, 'Хэрэглэгч цуцалсан', $this->advisor);

        $this->assertSame(AppointmentStatus::Cancelled, $first->refresh()->status);
        $this->assertSame(0, $first->reservations()->where('active', true)->count());
        $this->assertSame(AppointmentStatus::Confirmed, $this->appointmentFor($customer, $vehicle, '2026-11-05 10:00')->status);
    }

    public function test_stale_version_reschedule_is_rejected_and_conflicting_move_reverts(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $appointment = $this->appointmentFor($customer, $vehicle, '2026-11-05 10:00');
        $blocker = $this->appointmentFor($customer, $vehicle, '2026-11-05 13:00');

        $reschedule = app(RescheduleAppointment::class);
        $reschedule->handle($appointment, $this->localTime('2026-11-05 11:00'), $this->localTime('2026-11-05 12:00'), $this->bay->id, $this->mechanic->id, 1, $this->advisor);

        try {
            $reschedule->handle($appointment->refresh(), $this->localTime('2026-11-05 15:00'), $this->localTime('2026-11-05 16:00'), $this->bay->id, $this->mechanic->id, 1, $this->advisor);
            $this->fail('Stale version must be rejected');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('өөр ажилтан', $exception->getMessage());
        }

        try {
            $reschedule->handle($appointment->refresh(), $this->localTime('2026-11-05 13:30'), $this->localTime('2026-11-05 14:30'), $this->bay->id, $this->mechanic->id, 2, $this->advisor);
            $this->fail('Conflict must be rejected');
        } catch (SchedulingConflict) {
            $this->assertSame('11:00', BusinessTime::format($appointment->refresh()->planned_start, 'H:i'));
            $this->assertSame(1, $appointment->reservations()->where('active', true)->where('resource_type', 'bay')->count());
        }

        $this->assertSame(AppointmentStatus::Confirmed, $blocker->refresh()->status);
    }

    public function test_business_time_is_independent_of_viewer_timezone(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $appointment = $this->appointmentFor($customer, $vehicle, '2026-11-05 10:00');

        $this->assertSame('2026-11-05T02:00:00+00:00', $appointment->planned_start->toIso8601String());
        $this->assertSame('2026-11-05 10:00', BusinessTime::format($appointment->planned_start));

        // The calendar receives offset-free business wall-clock values and renders them in a fixed UTC frame,
        // so a browser in Tokyo or UTC still shows 10:00 Mongolia time.
        $this->actingAs($this->advisor);
        $events = (new ScheduleCalendar)->events('2026-11-02T00:00:00', '2026-11-09T00:00:00');
        $this->assertSame('2026-11-05T10:00:00', $events[0]['start']);
        $this->assertSame('2026-11-05T11:00:00', $events[0]['end']);
    }

    public function test_overrun_blocks_a_new_check_in_on_the_occupied_bay(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $this->travelTo($this->localTime('2026-11-04 09:55'));
        $first = $this->appointmentFor($customer, $vehicle, '2026-11-04 10:00');
        app(CheckInVehicle::class)->fromAppointment($first, ['complaint' => 'Тос', 'intake_km' => 1000], $this->advisor);

        [$other, $otherVehicle] = $this->customerWithVehicle();
        $second = $this->appointmentFor($other, $otherVehicle, '2026-11-04 11:00');

        $this->travelTo($this->localTime('2026-11-04 11:05'));

        $this->expectException(SchedulingConflict::class);
        app(CheckInVehicle::class)->fromAppointment($second, ['complaint' => 'Тоормос', 'intake_km' => 5000], $this->advisor);
    }

    public function test_open_occupancy_prevents_new_overlapping_reservations_even_after_planned_end(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $this->travelTo($this->localTime('2026-11-04 09:55'));
        $first = $this->appointmentFor($customer, $vehicle, '2026-11-04 10:00');
        app(CheckInVehicle::class)->fromAppointment($first, ['complaint' => 'Тос', 'intake_km' => 1000], $this->advisor);

        $this->travelTo($this->localTime('2026-11-04 12:00'));
        $otherMechanic = User::factory()->mechanic()->create();

        $this->expectException(SchedulingConflict::class);
        app(ConfirmAppointment::class)->handle([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
            'start' => $this->localTime('2026-11-04 12:00'), 'end' => $this->localTime('2026-11-04 13:00'),
            'bay_id' => $this->bay->id, 'mechanic_user_id' => $otherMechanic->id,
        ], $this->advisor);
    }

    public function test_converting_a_request_twice_returns_the_same_appointment(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $request = ServiceRequest::query()->create([
            'reference' => 'H-TEST01', 'guest_session_hash' => 'x', 'idempotency_key' => 'k', 'request_type' => 'workshop',
            'name' => 'А', 'phone_display' => '9911 2233', 'phone_normalized' => '+97699112233', 'vehicle_text' => 'Prius',
            'symptoms' => 'Тест', 'contact_consent_at' => now(), 'contact_consent_version' => 'v', 'status' => 'new',
        ]);

        $data = [
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'service_request_id' => $request->id,
            'start' => $this->localTime('2026-11-05 10:00'), 'end' => $this->localTime('2026-11-05 11:00'),
            'bay_id' => $this->bay->id, 'mechanic_user_id' => $this->mechanic->id,
        ];

        $first = app(ConfirmAppointment::class)->handle($data, $this->advisor);
        $second = app(ConfirmAppointment::class)->handle($data, $this->advisor);

        $this->assertTrue($first->is($second));
        $this->assertSame(2, ResourceReservation::query()->count());
    }

    public function test_mechanic_cannot_confirm_appointments(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();

        $this->expectException(DomainException::class);
        app(ConfirmAppointment::class)->handle([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id,
            'start' => $this->localTime('2026-11-05 10:00'), 'end' => $this->localTime('2026-11-05 11:00'),
            'bay_id' => $this->bay->id, 'mechanic_user_id' => $this->mechanic->id,
        ], $this->mechanic);
    }
}
