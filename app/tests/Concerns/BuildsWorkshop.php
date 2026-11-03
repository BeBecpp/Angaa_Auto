<?php

namespace Tests\Concerns;

use App\Actions\Customers\CustomerService;
use App\Actions\Scheduling\CheckInVehicle;
use App\Actions\Scheduling\ConfirmAppointment;
use App\Actions\Workshop\EstimateService;
use App\Actions\Workshop\TransitionWorkOrder;
use App\Enums\VehicleRelationship;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\EstimateRevision;
use App\Models\OpeningRule;
use App\Models\ShopBay;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * Builds realistic state through the real domain actions.
 */
trait BuildsWorkshop
{
    protected User $owner;

    protected User $advisor;

    protected User $mechanic;

    protected ShopBay $bay;

    protected function setUpWorkshop(): void
    {
        $this->owner = User::factory()->owner()->create();
        $this->advisor = User::factory()->advisor()->create();
        $this->mechanic = User::factory()->mechanic()->create();
        $this->bay = ShopBay::factory()->create();

        foreach (range(1, 7) as $day) {
            OpeningRule::query()->create(['scope' => 'shop', 'weekday' => $day, 'local_start' => '09:00', 'local_end' => '18:00']);
        }
    }

    /**
     * @return array{0: Customer, 1: Vehicle}
     */
    protected function customerWithVehicle(?User $portalUser = null): array
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->create();
        app(CustomerService::class)->grantVehicleAccess($customer, $vehicle, VehicleRelationship::Owner, $this->advisor);

        if ($portalUser) {
            $customer->forceFill(['user_id' => $portalUser->id, 'normalized_contact_phone' => $portalUser->phone_normalized])->save();
        }

        return [$customer, $vehicle];
    }

    protected function localTime(string $value): CarbonImmutable
    {
        return BusinessTime::fromLocal($value);
    }

    protected function appointmentFor(Customer $customer, Vehicle $vehicle, string $localStart = '+1 day 10:00', int $minutes = 60): Appointment
    {
        $start = str_starts_with($localStart, '+')
            ? BusinessTime::now()->modify($localStart)->utc()
            : $this->localTime($localStart);

        return app(ConfirmAppointment::class)->handle([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start' => CarbonImmutable::instance($start),
            'end' => CarbonImmutable::instance($start)->addMinutes($minutes),
            'bay_id' => $this->bay->id,
            'mechanic_user_id' => $this->mechanic->id,
        ], $this->advisor);
    }

    protected function checkedInOrder(Customer $customer, Vehicle $vehicle): WorkOrder
    {
        $appointment = $this->appointmentFor($customer, $vehicle, '+0 day 10:00');

        return app(CheckInVehicle::class)->fromAppointment($appointment, ['complaint' => 'Шажигнана', 'intake_km' => 100000], $this->advisor);
    }

    protected function orderWithSentEstimate(Customer $customer, Vehicle $vehicle): array
    {
        $order = $this->checkedInOrder($customer, $vehicle);
        app(TransitionWorkOrder::class)->handle($order, WorkOrderStatus::Diagnosing, $this->mechanic);

        $estimates = app(EstimateService::class);
        $draft = $estimates->createDraft($order->refresh(), $this->advisor);
        $estimates->saveDraft($draft, [], [
            ['kind' => 'labor', 'description' => 'Дэвсгэр солих', 'quantity' => '1', 'unit_price_mnt' => 35000],
            ['kind' => 'part', 'description' => 'Дэвсгэр', 'quantity' => '1', 'unit_price_mnt' => 68000],
        ], $this->advisor);

        $revision = $estimates->publish($draft, $this->advisor);

        return [$order->refresh(), $revision->refresh()];
    }

    protected function revision(EstimateRevision $revision): EstimateRevision
    {
        return $revision->refresh();
    }
}
