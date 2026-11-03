<?php

namespace App\Filament\Resources\MaintenancePlans;

use App\Actions\Maintenance\EvaluateDuePlans;
use App\Actions\Maintenance\MaintenancePlanService;
use App\Enums\PlanStatus;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\MaintenancePlans\Pages\ManageMaintenancePlans;
use App\Filament\Support\Notify;
use App\Models\MaintenancePlan;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class MaintenancePlanResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = MaintenancePlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Сануулга';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'үйлчилгээний төлөвлөгөө';

    protected static ?string $pluralModelLabel = 'Дараагийн үйлчилгээ';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $evaluator = app(EvaluateDuePlans::class);

        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('status')->label('Төлөв')->badge(),
                TextColumn::make('vehicle.current_plate_display')->label('Машин')->description(fn (MaintenancePlan $record): string => $record->vehicle->make.' '.$record->vehicle->model)->searchable(),
                TextColumn::make('customer.display_name')->label('Үйлчлүүлэгч')->searchable(),
                TextColumn::make('description')->label('Үйлчилгээ')->wrap(),
                TextColumn::make('due_date')->label('Огноо')->date('Y-m-d')->placeholder('—')->sortable()
                    ->color(fn (MaintenancePlan $record): ?string => $record->due_date?->isPast() && $record->status === PlanStatus::Active ? 'danger' : null),
                TextColumn::make('due_km')->label('Км')->numeric(thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('mileage')->label('Одоогийн км')->state(function (MaintenancePlan $record) use ($evaluator): string {
                    if (! $record->due_km) {
                        return '—';
                    }

                    $reading = $evaluator->credibleReading($record, CarbonImmutable::now());

                    return $reading ? number_format($reading->km, 0, '.', ' ').' ('.$reading->observed_at->format('Y-m-d').')' : 'Тодорхойгүй';
                }),
                TextColumn::make('eligible')->label('Сануулга')->state(fn (MaintenancePlan $record): string => $record->status !== PlanStatus::Active ? '—' : ($evaluator->eligible($record) ? 'Илгээх боломжтой' : 'Зөвшөөрөл/эзэмшил алга'))
                    ->color(fn (string $state): ?string => $state === 'Илгээх боломжтой' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Төлөв')->options(PlanStatus::class)->default(PlanStatus::Active->value),
            ])
            ->recordActions([
                ActionGroup::make(collect(PlanStatus::cases())->map(fn (PlanStatus $status): Action => Action::make('status_'.$status->value)
                    ->label($status->getLabel().' болгох')
                    ->visible(fn (MaintenancePlan $record): bool => $record->status !== $status)
                    ->schema([Textarea::make('reason')->label('Шалтгаан')])
                    ->action(fn (MaintenancePlan $record, array $data) => Notify::attempt(fn () => app(MaintenancePlanService::class)->changeStatus($record, $status, auth()->user(), $data['reason'] ?? null), 'Шинэчлэгдлээ')))->all()),
            ])
            ->emptyStateHeading('Төлөвлөгөө алга')
            ->emptyStateDescription('Ажлын захиалгаас «Дараагийн үйлчилгээ» үйлдлээр нэмнэ.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMaintenancePlans::route('/')];
    }
}
