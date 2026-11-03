<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Actions\Scheduling\ConfirmAppointment;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Support\Notify;
use App\Filament\Support\ScheduleFields;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Vehicle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff-created appointment for an existing customer (phone booking or walk-in scheduling).
 */
class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Үйлчлүүлэгч ба машин')->columns(2)->schema([
                Select::make('customer_id')->label('Үйлчлүүлэгч')->searchable()->required()->live()
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()->whereNull('archived_at')
                        ->where(fn ($query) => $query->where('display_name', 'like', "%{$search}%")->orWhere('normalized_contact_phone', 'like', '%'.preg_replace('/\D/', '', $search).'%'))
                        ->limit(20)->get()->mapWithKeys(fn (Customer $customer): array => [$customer->id => $customer->display_name.' · '.$customer->contact_phone])->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->display_name),
                Select::make('vehicle_id')->label('Машин')->required()
                    ->options(fn (Get $get): array => $get('customer_id') ? Customer::query()->find($get('customer_id'))?->currentVehicles()->get()->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->id => $vehicle->label()])->all() ?? [] : []),
                Select::make('service_id')->label('Үйлчилгээ')->options(fn (): array => Service::query()->where('active', true)->pluck('name_mn', 'id')->all()),
            ]),
            Section::make('Цаг')->schema([
                ...ScheduleFields::make(),
                Textarea::make('staff_notes')->label('Дотоод тэмдэглэл'),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        [$start, $end] = ScheduleFields::interval($data);

        return Notify::attempt(fn () => app(ConfirmAppointment::class)->handle([
            'customer_id' => (int) $data['customer_id'],
            'vehicle_id' => (int) $data['vehicle_id'],
            'service_id' => $data['service_id'] ?? null,
            'type' => $data['type'] ?? 'workshop',
            'start' => $start,
            'end' => $end,
            'bay_id' => $data['bay_id'] ?? null,
            'mechanic_user_id' => (int) $data['mechanic_user_id'],
            'buffer_min' => (int) ($data['buffer_min'] ?? 0),
            'travel_min' => (int) ($data['travel_min'] ?? 0),
            'staff_notes' => $data['staff_notes'] ?? null,
        ], auth()->user()), 'Цаг баталгаажлаа');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
