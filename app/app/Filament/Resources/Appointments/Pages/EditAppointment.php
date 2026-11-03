<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Actions\Scheduling\CancelAppointment;
use App\Actions\Scheduling\CheckInVehicle;
use App\Actions\Scheduling\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Filament\Concerns\RefreshesRecordAfterActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\Notify;
use App\Filament\Support\ScheduleFields;
use App\Models\Appointment;
use App\Support\Audit;
use App\Support\BusinessTime;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Appointment editor. Saving runs the same reschedule action as calendar drag/drop, with an optimistic
 * version check so a stale tab cannot overwrite someone else's change.
 */
class EditAppointment extends EditRecord
{
    use RefreshesRecordAfterActions;

    protected static string $resource = AppointmentResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Мэдээлэл')->columns(3)->schema([
                Placeholder::make('status_view')->label('Төлөв')->content(fn (Appointment $record): string => $record->status->getLabel()),
                Placeholder::make('customer_view')->label('Үйлчлүүлэгч')->content(fn (Appointment $record): string => $record->customer->display_name.' · '.$record->customer->contact_phone),
                Placeholder::make('vehicle_view')->label('Машин')->content(fn (Appointment $record): string => $record->vehicle?->label() ?? '—'),
                Placeholder::make('change_view')->label('Хэрэглэгчийн хүсэлт')->content(fn (Appointment $record): string => $record->customer_change_request ?? '—')->columnSpanFull()
                    ->visible(fn (Appointment $record): bool => $record->customer_change_request !== null),
                Placeholder::make('request_view')->label('Анхны хүсэлт')->content(fn (Appointment $record): string => $record->serviceRequest ? $record->serviceRequest->reference.' — '.$record->serviceRequest->symptoms : '—')->columnSpanFull(),
            ]),
            Section::make('Цаг өөрчлөх')->description('Хадгалахад пост/механикийн давхардлыг сервер дээр шалгана.')
                ->disabled(fn (Appointment $record): bool => $record->status !== AppointmentStatus::Confirmed)
                ->schema([
                    ...ScheduleFields::make(withType: false),
                    Hidden::make('version'),
                ]),
            Section::make('Тэмдэглэл')->schema([
                Textarea::make('staff_notes')->label('Дотоод тэмдэглэл'),
            ]),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $start = BusinessTime::local($this->record->planned_start);

        return $data + [
            'date' => $start->toDateString(),
            'time' => $start->format('H:i'),
            'duration_min' => (int) $this->record->planned_start->diffInMinutes($this->record->planned_end),
            'type' => $this->record->type->value,
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Appointment $record */
        $record->forceFill(['staff_notes' => $data['staff_notes'] ?? null])->save();

        if ($record->status !== AppointmentStatus::Confirmed || ! isset($data['date'])) {
            return $record;
        }

        [$start, $end] = ScheduleFields::interval($data);
        $unchanged = $start->equalTo($record->planned_start) && $end->equalTo($record->planned_end)
            && (int) ($data['bay_id'] ?? 0) === (int) $record->bay_id
            && (int) $data['mechanic_user_id'] === (int) $record->mechanic_user_id
            && (int) ($data['buffer_min'] ?? 0) === (int) $record->buffer_min;

        if ($unchanged) {
            return $record;
        }

        return Notify::attempt(fn () => app(RescheduleAppointment::class)->handle(
            $record,
            $start,
            $end,
            isset($data['bay_id']) ? (int) $data['bay_id'] : null,
            (int) $data['mechanic_user_id'],
            (int) $data['version'],
            auth()->user(),
            (int) ($data['buffer_min'] ?? 0),
        ), 'Цаг шинэчлэгдлээ');
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        $confirmed = fn (): bool => $this->record->status === AppointmentStatus::Confirmed;

        return [
            Action::make('checkin')->label('Машин хүлээн авах')->icon('heroicon-o-key')->color('primary')
                ->visible($confirmed)
                ->fillForm(fn (): array => ['complaint' => $this->record->serviceRequest?->symptoms])
                ->schema([
                    Textarea::make('complaint')->label('Гомдол')->required(),
                    TextInput::make('intake_km')->label('Гүйлт (км)')->integer()->minValue(0)->required(),
                    TextInput::make('fuel_level')->label('Түлш'),
                    TextInput::make('visible_damage_note')->label('Харагдах гэмтэл'),
                    TextInput::make('belongings_note')->label('Үлдээсэн эд зүйл'),
                    TextInput::make('km_correction_note')->label('Км буурсан бол залруулгын шалтгаан'),
                ])
                ->action(function (array $data): void {
                    $order = Notify::attempt(fn () => app(CheckInVehicle::class)->fromAppointment($this->record, $data + ['intake_km' => (int) $data['intake_km']], auth()->user()), 'Машин хүлээн авлаа');
                    $this->redirect(WorkOrderResource::getUrl('view', ['record' => $order]));
                }),
            Action::make('clearChange')->label('Хэрэглэгчийн хүсэлтийг шийдсэн')->icon('heroicon-o-check')->color('gray')
                ->visible(fn (): bool => $this->record->customer_change_request !== null)
                ->schema([Textarea::make('note')->label('Шийдвэр (тэмдэглэл)')->required()])
                ->action(function (array $data): void {
                    Audit::record('appointment.change_request_resolved', $this->record, reason: $data['note']);
                    $this->record->forceFill(['customer_change_request' => null, 'customer_change_requested_at' => null])->save();
                }),
            Action::make('noShow')->label('Ирээгүй')->icon('heroicon-o-user-minus')->color('warning')
                ->visible(fn (): bool => $this->record->status === AppointmentStatus::Confirmed && $this->record->planned_start->isPast())
                ->schema([Textarea::make('reason')->label('Тэмдэглэл')->required()->default('Цагтаа ирээгүй')])
                ->action(fn (array $data) => Notify::attempt(fn () => app(CancelAppointment::class)->handle($this->record, $data['reason'], auth()->user(), noShow: true), 'Ирээгүй гэж тэмдэглэлээ')),
            Action::make('cancel')->label('Цуцлах')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [AppointmentStatus::Requested, AppointmentStatus::Confirmed], true))
                ->schema([Textarea::make('reason')->label('Шалтгаан')->required()])
                ->action(fn (array $data) => Notify::attempt(fn () => app(CancelAppointment::class)->handle($this->record, $data['reason'], auth()->user()), 'Цаг цуцлагдлаа')),
            Action::make('order')->label('Ажлын захиалга')->icon('heroicon-o-wrench-screwdriver')->color('gray')
                ->visible(fn (): bool => $this->record->workOrder !== null)
                ->url(fn (): ?string => $this->record->workOrder ? WorkOrderResource::getUrl('view', ['record' => $this->record->workOrder]) : null),
        ];
    }
}
