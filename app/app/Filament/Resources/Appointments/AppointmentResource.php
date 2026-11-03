<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentStatus;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Support\ScheduleFields;
use App\Models\Appointment;
use App\Models\ShopBay;
use App\Support\BusinessTime;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AppointmentResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Цагийн хуваарь';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'цаг';

    protected static ?string $pluralModelLabel = 'Цагийн жагсаалт';

    public static function getNavigationBadge(): ?string
    {
        $count = Appointment::query()->whereNotNull('customer_change_requested_at')->where('status', AppointmentStatus::Confirmed)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('planned_start', 'asc')
            ->columns([
                TextColumn::make('planned_start')->label('Цаг (УБ)')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'Y-m-d H:i'))->sortable(),
                TextColumn::make('status')->label('Төлөв')->badge(),
                TextColumn::make('vehicle.current_plate_display')->label('Дугаар')->description(fn (Appointment $record): string => trim(($record->vehicle?->make ?? '').' '.($record->vehicle?->model ?? '')))->searchable(),
                TextColumn::make('customer.display_name')->label('Үйлчлүүлэгч')->searchable(),
                TextColumn::make('service.name_mn')->label('Үйлчилгээ')->placeholder('—'),
                TextColumn::make('bay.name')->label('Пост')->placeholder('—'),
                TextColumn::make('mechanic.name')->label('Механик'),
                TextColumn::make('customer_change_request')->label('Хэрэглэгчийн хүсэлт')->placeholder('—')->wrap()->color('warning'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Төлөв')->options(AppointmentStatus::class)->multiple()->default([AppointmentStatus::Confirmed->value, AppointmentStatus::Arrived->value]),
                SelectFilter::make('bay_id')->label('Пост')->options(fn (): array => ShopBay::query()->pluck('name', 'id')->all()),
                SelectFilter::make('mechanic_user_id')->label('Механик')->options(fn (): array => ScheduleFields::mechanics()),
                Filter::make('day')->label('Өдөр')
                    ->schema([DatePicker::make('day')->label('Өдөр (УБ)')->native(false)->firstDayOfWeek(1)])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['day'])) {
                            return $query;
                        }

                        $start = BusinessTime::fromLocal($data['day'].' 00:00');

                        return $query->whereBetween('planned_start', [$start, $start->addDay()]);
                    }),
                Filter::make('change_requested')->label('Хэрэглэгч өөрчлөлт хүссэн')->query(fn (Builder $query): Builder => $query->whereNotNull('customer_change_requested_at')),
            ])
            ->recordActions([EditAction::make()->label('Нээх')])
            ->emptyStateHeading('Цаг алга');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointment::route('/create'),
            'edit' => EditAppointment::route('/{record}/edit'),
        ];
    }
}
