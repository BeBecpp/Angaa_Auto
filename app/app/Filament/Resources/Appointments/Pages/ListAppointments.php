<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Pages\ScheduleCalendar;
use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')->label('Календарь')->icon('heroicon-o-calendar')->color('gray')->url(ScheduleCalendar::getUrl()),
            CreateAction::make()->label('Шинэ цаг'),
        ];
    }
}
