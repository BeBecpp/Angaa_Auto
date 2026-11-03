<?php

namespace App\Filament\Pages;

use App\Actions\Scheduling\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Support\ScheduleFields;
use App\Models\Appointment;
use App\Models\ResourceReservation;
use App\Models\ShopBay;
use App\Support\BusinessTime;
use App\Support\DomainException;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * FullCalendar Standard (MIT) day/week/month/list with bay and mechanic filters. Drag/drop and resize
 * ask for confirmation and call the same reschedule action as the editor; conflicts revert.
 */
class ScheduleCalendar extends Page
{
    protected string $view = 'filament.pages.schedule-calendar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Цагийн хуваарь';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Календарь';

    protected static ?string $title = 'Календарь';

    protected static ?string $slug = 'calendar';

    public string $bayFilter = '';

    public string $mechanicFilter = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(StaffRole::Owner, StaffRole::Advisor) ?? false;
    }

    public function updated(): void
    {
        $this->dispatch('calendar-refresh');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(string $start, string $end): array
    {
        $from = BusinessTime::fromLocal($start);
        $to = BusinessTime::fromLocal($end);

        $appointments = Appointment::query()
            ->with(['vehicle', 'service', 'mechanic', 'bay'])
            ->where('planned_start', '<', $to)
            ->where('planned_end', '>', $from)
            ->when($this->bayFilter !== '', fn ($query) => $query->where('bay_id', (int) $this->bayFilter))
            ->when($this->mechanicFilter !== '', fn ($query) => $query->where('mechanic_user_id', (int) $this->mechanicFilter))
            ->get();

        $now = CarbonImmutable::now();
        $events = [];

        foreach ($appointments as $appointment) {
            $title = trim(($appointment->vehicle?->current_plate_display ?? '').' '.($appointment->vehicle?->model ?? $appointment->vehicle?->make ?? ''));
            $detail = implode(' · ', array_filter([$appointment->service?->name_mn, $appointment->mechanic?->name, $appointment->bay?->name]));

            $events[] = [
                'id' => (string) $appointment->id,
                'title' => $title.($detail ? ' — '.$detail : ''),
                'start' => BusinessTime::format($appointment->planned_start, 'Y-m-d\TH:i:s'),
                'end' => BusinessTime::format($appointment->planned_end, 'Y-m-d\TH:i:s'),
                'backgroundColor' => $this->color($appointment->status),
                'borderColor' => $this->color($appointment->status),
                'textColor' => $appointment->status === AppointmentStatus::Requested ? '#142632' : '#ffffff',
                'classNames' => in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true) ? ['opacity-50', 'line-through'] : [],
                'extendedProps' => [
                    'version' => $appointment->version,
                    'movable' => $appointment->status === AppointmentStatus::Confirmed && $appointment->planned_start->isFuture(),
                    'statusLabel' => $appointment->status->getLabel(),
                    'tooltip' => $title."\n".$detail."\n".$appointment->status->getLabel(),
                ],
                'editable' => $appointment->status === AppointmentStatus::Confirmed && $appointment->planned_start->isFuture(),
            ];
        }

        $overruns = ResourceReservation::query()
            ->with(['bay', 'appointment.vehicle', 'workOrder'])
            ->where('resource_type', 'bay')
            ->whereNotNull('actual_occupied_from')
            ->whereNull('actual_occupied_to')
            ->where('ends_at', '<', $now)
            ->when($this->bayFilter !== '', fn ($query) => $query->where('bay_id', (int) $this->bayFilter))
            ->get();

        foreach ($overruns as $reservation) {
            $events[] = [
                'id' => 'overrun-'.$reservation->id,
                'title' => '⚠ Хугацаа хэтэрсэн: '.($reservation->bay?->name ?? '').' '.($reservation->workOrder?->order_number ?? ''),
                'start' => BusinessTime::format($reservation->ends_at, 'Y-m-d\TH:i:s'),
                'end' => BusinessTime::format($now->addMinutes(15), 'Y-m-d\TH:i:s'),
                'backgroundColor' => '#B42318',
                'borderColor' => '#B42318',
                'textColor' => '#ffffff',
                'editable' => false,
                'extendedProps' => ['movable' => false, 'statusLabel' => 'Пост эзэлсэн хэвээр', 'tooltip' => 'Төлөвлөсөн хугацаанаас хэтэрсэн; дараагийн цагуудыг шалгана уу.'],
            ];
        }

        return $events;
    }

    public function moveAppointment(string $id, string $start, string $end, int $version): bool
    {
        $appointment = Appointment::query()->find((int) $id);

        if (! $appointment) {
            return false;
        }

        try {
            app(RescheduleAppointment::class)->handle(
                $appointment,
                BusinessTime::fromLocal($start),
                BusinessTime::fromLocal($end),
                $appointment->bay_id,
                $appointment->mechanic_user_id,
                $version,
                auth()->user(),
            );
        } catch (DomainException $exception) {
            Notification::make()->danger()->title('Цаг өөрчлөгдсөнгүй')->body($exception->getMessage())->send();

            return false;
        }

        Notification::make()->success()->title('Цаг шинэчлэгдлээ')->send();

        return true;
    }

    public function openAppointment(string $id): void
    {
        if (str_starts_with($id, 'overrun-')) {
            return;
        }

        $this->redirect(AppointmentResource::getUrl('edit', ['record' => (int) $id]));
    }

    private function color(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::Requested => '#E6AA42',
            AppointmentStatus::Confirmed => '#0B5A60',
            AppointmentStatus::Arrived => '#142632',
            AppointmentStatus::Fulfilled => '#187047',
            AppointmentStatus::Cancelled, AppointmentStatus::NoShow => '#58656C',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'bays' => ShopBay::query()->where('active', true)->orderBy('sort_order')->pluck('name', 'id'),
            'mechanics' => ScheduleFields::mechanics(),
        ];
    }
}
