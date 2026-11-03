<?php

namespace App\Filament\Resources\WorkOrders;

use App\Enums\StaffRole;
use App\Enums\WorkOrderStatus;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\WorkOrders\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ListWorkOrders;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Filament\Support\ScheduleFields;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class WorkOrderResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = WorkOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'Засвар';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'ажлын захиалга';

    protected static ?string $pluralModelLabel = 'Ажлын захиалга';

    protected static ?string $recordTitleAttribute = 'order_number';

    /**
     * Mechanics use the phone screen; the panel list is front-office.
     *
     * @return list<StaffRole>
     */
    protected static function viewRoles(): array
    {
        return [StaffRole::Owner, StaffRole::Advisor];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number', 'vehicle.current_plate_normalized', 'customer.display_name'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('order_number')->label('Дугаар')->searchable()->weight('bold'),
                TextColumn::make('status')->label('Төлөв')->badge(),
                TextColumn::make('vehicle.current_plate_display')->label('Машин')->description(fn (WorkOrder $record): string => trim(($record->vehicle_snapshot['make'] ?? '').' '.($record->vehicle_snapshot['model'] ?? '')))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('vehicle', fn ($inner) => $inner->where('current_plate_normalized', 'like', '%'.mb_strtoupper(str_replace(' ', '', $search)).'%'))),
                TextColumn::make('customer.display_name')->label('Үйлчлүүлэгч')->searchable(),
                TextColumn::make('mechanic.name')->label('Механик')->placeholder('—'),
                TextColumn::make('received_at')->label('Хүлээн авсан')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'm/d H:i'))->sortable(),
                TextColumn::make('promised_ready_at')->label('Амласан')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'm/d H:i'))->placeholder('—')
                    ->color(fn (WorkOrder $record): ?string => $record->promised_ready_at?->isPast() && $record->status->isOpen() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('status')->label('Төлөв')->options(WorkOrderStatus::class)->multiple(),
                Filter::make('open')->label('Нээлттэй')->default()->query(fn (Builder $query): Builder => $query->whereNotIn('status', [WorkOrderStatus::Delivered, WorkOrderStatus::Cancelled])),
                SelectFilter::make('assigned_mechanic_id')->label('Механик')->options(fn (): array => ScheduleFields::mechanics()),
                Filter::make('overdue')->label('Амласан хугацаа хэтэрсэн')->query(fn (Builder $query): Builder => $query->where('promised_ready_at', '<', now())->whereNotIn('status', [WorkOrderStatus::Delivered, WorkOrderStatus::Cancelled, WorkOrderStatus::ReadyForPickup])),
            ])
            ->recordActions([ViewAction::make()->label('Нээх')])
            ->emptyStateHeading('Ажлын захиалга алга');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Grid::make(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('status')->label('Төлөв')->badge(),
                    TextEntry::make('customer.display_name')->label('Үйлчлүүлэгч')->helperText(fn (WorkOrder $record): ?string => $record->customer->contact_phone),
                    TextEntry::make('vehicle_label')->label('Машин (хүлээн авах үеийн)')->state(fn (WorkOrder $record): string => trim(implode(' ', array_filter($record->vehicle_snapshot)))),
                    TextEntry::make('intake_km')->label('Км')->numeric(thousandsSeparator: ' ')->placeholder('—'),
                    TextEntry::make('mechanic.name')->label('Механик')->placeholder('—'),
                    TextEntry::make('bay.name')->label('Пост')->placeholder('—'),
                    TextEntry::make('received_at')->label('Хүлээн авсан')->formatStateUsing(fn ($state): string => BusinessTime::format($state)),
                    TextEntry::make('promised_ready_at')->label('Амласан')->formatStateUsing(fn ($state): string => BusinessTime::format($state))->placeholder('—'),
                ]),
                TextEntry::make('complaint')->label('Гомдол')->columnSpanFull(),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextEntry::make('visible_damage_note')->label('Харагдах гэмтэл')->placeholder('—'),
                    TextEntry::make('belongings_note')->label('Эд зүйл')->placeholder('—'),
                    TextEntry::make('fuel_level')->label('Түлш')->placeholder('—'),
                ]),
                TextEntry::make('owner_override_reason')->label('Эзний онцгой зөвшөөрөл')->visible(fn (WorkOrder $record): bool => $record->owner_override_at !== null)->color('warning'),
                TextEntry::make('unpaid_delivery_reason')->label('Үлдэгдэлтэй олгосон шалтгаан')->visible(fn (WorkOrder $record): bool => $record->unpaid_delivery_reason !== null)->color('danger'),
            ]),
            Tabs::make('details')->columnSpanFull()->tabs([
                Tab::make('Үнийн санал')->schema([
                    View::make('filament.work-order.estimates')->viewData(fn (WorkOrder $record): array => ['order' => $record]),
                ]),
                Tab::make('Үзлэг ба зураг')->schema([
                    View::make('filament.work-order.inspection')->viewData(fn (WorkOrder $record): array => ['order' => $record]),
                    View::make('filament.media-grid')->viewData(fn (WorkOrder $record): array => ['media' => $record->media()->latest()->get()]),
                ]),
                Tab::make('Засвар ба QC')->schema([
                    View::make('filament.work-order.repair')->viewData(fn (WorkOrder $record): array => ['order' => $record]),
                ]),
                Tab::make('Төлбөр ба дараагийн үйлчилгээ')->schema([
                    View::make('filament.work-order.finance')->viewData(fn (WorkOrder $record): array => ['order' => $record]),
                ]),
                Tab::make('Түүх')->schema([
                    View::make('filament.work-order.history')->viewData(fn (WorkOrder $record): array => ['order' => $record]),
                ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrders::route('/'),
            'create' => CreateWorkOrder::route('/create'),
            'view' => ViewWorkOrder::route('/{record}'),
        ];
    }
}
