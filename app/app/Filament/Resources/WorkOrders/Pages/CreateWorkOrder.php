<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Actions\Scheduling\CheckInVehicle;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\Notify;
use App\Filament\Support\ScheduleFields;
use App\Models\Customer;
use App\Models\ShopBay;
use App\Models\Vehicle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Walk-in intake: reserves the bay/mechanic from now through the same conflict guard.
 */
class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected static ?string $title = 'Шууд ирсэн машин хүлээн авах';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Үйлчлүүлэгч ба машин')->description('Шинэ үйлчлүүлэгч бол эхлээд «Үйлчлүүлэгч» хэсэгт бүртгэж машиныг холбоно.')->columns(2)->schema([
                Select::make('customer_id')->label('Үйлчлүүлэгч')->searchable()->required()->live()
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()->whereNull('archived_at')
                        ->where(fn ($query) => $query->where('display_name', 'like', "%{$search}%")->orWhere('normalized_contact_phone', 'like', '%'.preg_replace('/\D/', '', $search).'%'))
                        ->limit(20)->get()->mapWithKeys(fn (Customer $customer): array => [$customer->id => $customer->display_name.' · '.$customer->contact_phone])->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->display_name),
                Select::make('vehicle_id')->label('Машин')->required()
                    ->options(fn (Get $get): array => $get('customer_id') ? Customer::query()->find($get('customer_id'))?->currentVehicles()->get()->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->id => $vehicle->label()])->all() ?? [] : []),
            ]),
            Section::make('Хүлээн авах')->columns(2)->schema([
                Textarea::make('complaint')->label('Гомдол')->required()->columnSpanFull(),
                TextInput::make('intake_km')->label('Км')->integer()->minValue(0)->required(),
                TextInput::make('fuel_level')->label('Түлш'),
                TextInput::make('visible_damage_note')->label('Харагдах гэмтэл'),
                TextInput::make('belongings_note')->label('Эд зүйл'),
                TextInput::make('km_correction_note')->label('Км буурсан бол залруулгын шалтгаан')->columnSpanFull(),
            ]),
            Section::make('Пост ба механик')->columns(3)->schema([
                Select::make('bay_id')->label('Пост')->options(fn (): array => ShopBay::query()->where('active', true)->pluck('name', 'id')->all())->required(),
                Select::make('mechanic_user_id')->label('Механик')->options(fn (): array => ScheduleFields::mechanics())->required(),
                TextInput::make('duration_min')->label('Барагцаа хугацаа (мин)')->integer()->default(60)->required(),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return Notify::attempt(fn () => app(CheckInVehicle::class)->walkIn(
            Customer::query()->findOrFail($data['customer_id']),
            Customer::query()->findOrFail($data['customer_id'])->currentVehicles()->findOrFail($data['vehicle_id']),
            [
                'complaint' => $data['complaint'],
                'intake_km' => (int) $data['intake_km'],
                'fuel_level' => $data['fuel_level'] ?? null,
                'visible_damage_note' => $data['visible_damage_note'] ?? null,
                'belongings_note' => $data['belongings_note'] ?? null,
                'km_correction_note' => $data['km_correction_note'] ?? null,
                'bay_id' => (int) $data['bay_id'],
                'mechanic_user_id' => (int) $data['mechanic_user_id'],
                'duration_min' => (int) $data['duration_min'],
            ],
            auth()->user(),
        ), 'Машин хүлээн авлаа');
    }

    protected function getRedirectUrl(): string
    {
        return WorkOrderResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
