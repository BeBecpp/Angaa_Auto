<?php

namespace App\Filament\Support;

use App\Enums\AppointmentType;
use App\Models\ShopBay;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\EnumValue;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Shared scheduling form fields. Times are entered as Asia/Ulaanbaatar wall-clock values.
 */
class ScheduleFields
{
    /**
     * @return array<int, Component|Field>
     */
    public static function make(bool $withType = true): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 3])->schema(array_values(array_filter([
                $withType ? Select::make('type')->label('Төрөл')->options(AppointmentType::class)->default(AppointmentType::Workshop->value)->required()->live() : null,
                DatePicker::make('date')->label('Огноо (Улаанбаатар)')->native(false)->displayFormat('Y-m-d')->firstDayOfWeek(1)->required()->default(BusinessTime::today()->addDay()->toDateString()),
                TimePicker::make('time')->label('Эхлэх цаг')->seconds(false)->minutesStep(15)->required()->default('10:00'),
                TextInput::make('duration_min')->label('Үргэлжлэх (мин)')->integer()->minValue(15)->maxValue(600)->default(60)->required(),
                Select::make('bay_id')->label('Пост')->options(fn (): array => ShopBay::query()->where('active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->required(fn (Get $get): bool => (EnumValue::of($get('type')) ?? 'workshop') === 'workshop')
                    ->visible(fn (Get $get): bool => (EnumValue::of($get('type')) ?? 'workshop') === 'workshop'),
                Select::make('mechanic_user_id')->label('Механик')->options(fn (): array => self::mechanics())->required(),
                TextInput::make('buffer_min')->label('Дараагийн ажлын өмнөх зай (мин)')->integer()->minValue(0)->maxValue(120)->default(0),
                TextInput::make('travel_min')->label('Замын хугацаа (мин)')->integer()->minValue(0)->maxValue(240)->default(0)
                    ->visible(fn (Get $get): bool => (EnumValue::of($get('type')) ?? 'workshop') === 'callout')
                    ->required(fn (Get $get): bool => (EnumValue::of($get('type')) ?? 'workshop') === 'callout'),
            ]))),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function mechanics(): array
    {
        return User::query()
            ->where('type', 'staff')
            ->where('active', true)
            ->whereHas('staffProfile', fn ($query) => $query->where('bookable', true))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function interval(array $data): array
    {
        $time = substr((string) $data['time'], 0, 5);
        $start = BusinessTime::fromLocal(substr((string) $data['date'], 0, 10).' '.$time);

        return [$start, $start->addMinutes((int) $data['duration_min'])];
    }
}
