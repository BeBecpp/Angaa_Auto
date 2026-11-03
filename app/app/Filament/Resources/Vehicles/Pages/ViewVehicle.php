<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Actions\Vehicles\RecordOdometerReading;
use App\Filament\Concerns\RefreshesRecordAfterActions;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Filament\Support\Notify;
use App\Support\Audit;
use App\Support\Plate;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicle extends ViewRecord
{
    use RefreshesRecordAfterActions;

    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('odometer')->label('Км бүртгэх')->icon('heroicon-o-chart-bar')
                ->schema([
                    TextInput::make('km')->label('Км')->integer()->minValue(0)->required(),
                    DateTimePicker::make('observed_at')->label('Хэзээ харсан')->timezone('Asia/Ulaanbaatar')->seconds(false)->default(now())->required(),
                    TextInput::make('correction')->label('Өмнөхөөс бага бол залруулгын шалтгаан'),
                ])
                ->action(fn (array $data) => Notify::attempt(fn () => app(RecordOdometerReading::class)->handle(
                    $this->record,
                    (int) $data['km'],
                    auth()->user(),
                    CarbonImmutable::parse($data['observed_at']),
                    $data['correction'] ?? null,
                ), 'Км бүртгэгдлээ')),
            Action::make('edit')->label('Мэдээлэл засах')->icon('heroicon-o-pencil')->color('gray')
                ->fillForm(fn (): array => $this->record->only(['make', 'model', 'year', 'vin']) + ['plate' => $this->record->current_plate_display])
                ->schema([
                    TextInput::make('make')->label('Марк')->required(),
                    TextInput::make('model')->label('Загвар'),
                    TextInput::make('year')->label('Он')->integer(),
                    TextInput::make('plate')->label('Улсын дугаар')->helperText('Дугаар солигдож болно; машины түүх дугаараар бус машинаар хадгалагдана.'),
                    TextInput::make('vin')->label('VIN'),
                ])
                ->action(function (array $data): void {
                    $before = $this->record->current_plate_display;
                    $this->record->forceFill([
                        'make' => $data['make'], 'model' => $data['model'] ?? null, 'year' => $data['year'] ?? null,
                        'current_plate_display' => Plate::display($data['plate'] ?? null), 'current_plate_normalized' => Plate::normalize($data['plate'] ?? null),
                        'vin' => isset($data['vin']) && $data['vin'] !== '' ? strtoupper(trim($data['vin'])) : null,
                    ])->save();
                    Audit::record('vehicle.updated', $this->record, ['plate_before' => $before]);
                }),
        ];
    }
}
