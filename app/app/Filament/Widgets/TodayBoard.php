<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Support\BusinessTime;
use Filament\Widgets\Widget;

/**
 * Today's list: local time, plate/model, first name, service, bay/mechanic, state and next action.
 */
class TodayBoard extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.today-board';

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = BusinessTime::today();

        return [
            'appointments' => Appointment::query()
                ->with(['vehicle', 'customer', 'service', 'bay', 'mechanic', 'workOrder'])
                ->whereBetween('planned_start', [$today->utc(), $today->addDay()->utc()])
                ->whereNotIn('status', [AppointmentStatus::Cancelled])
                ->orderBy('planned_start')
                ->get(),
            'nextAction' => fn (Appointment $appointment): string => match (true) {
                $appointment->status === AppointmentStatus::Confirmed => 'Хүлээн авах',
                $appointment->workOrder?->status === WorkOrderStatus::AwaitingApproval => 'Зөвшөөрөл авах',
                $appointment->workOrder?->status === WorkOrderStatus::ReadyForPickup => 'Олгох',
                $appointment->workOrder !== null => $appointment->workOrder->status->getLabel(),
                default => $appointment->status->getLabel(),
            },
        ];
    }
}
