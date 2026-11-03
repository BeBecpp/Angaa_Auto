<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\ScheduleCalendar;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Primary operational row. Every count links to the filtered records.
 */
class OperationalStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = BusinessTime::today();

        $active = [WorkOrderStatus::Received, WorkOrderStatus::Diagnosing, WorkOrderStatus::AwaitingApproval, WorkOrderStatus::Approved, WorkOrderStatus::InProgress, WorkOrderStatus::WaitingParts, WorkOrderStatus::QualityCheck];

        return [
            Stat::make('Шинэ хүсэлт', ServiceRequest::query()->where('status', RequestStatus::New)->count())
                ->description('Хариу өгөөгүй')
                ->color('warning')
                ->url(ServiceRequestResource::getUrl()),
            Stat::make('Өнөөдөр ирэх', Appointment::query()->where('status', AppointmentStatus::Confirmed)->whereBetween('planned_start', [$today->utc(), $today->addDay()->utc()])->count())
                ->description('Баталгаажсан цаг')
                ->url(ScheduleCalendar::getUrl()),
            Stat::make('Засварт', WorkOrder::query()->whereIn('status', $active)->count())
                ->description('Нээлттэй ажил')
                ->url(WorkOrderResource::getUrl()),
            Stat::make('Авахад бэлэн', WorkOrder::query()->where('status', WorkOrderStatus::ReadyForPickup)->count())
                ->color('success')
                ->url(WorkOrderResource::getUrl('index', ['filters' => ['status' => ['values' => [WorkOrderStatus::ReadyForPickup->value]]]])),
        ];
    }
}
