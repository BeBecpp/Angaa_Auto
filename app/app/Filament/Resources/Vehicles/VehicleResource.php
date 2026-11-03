<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VehicleResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Үйлчлүүлэгч';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'машин';

    protected static ?string $pluralModelLabel = 'Машин ба түүх';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('current_plate_display')->label('Дугаар')->weight('bold')->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('current_plate_normalized', 'like', '%'.mb_strtoupper(preg_replace('/[\s\-]/u', '', $search)).'%')),
                TextColumn::make('make')->label('Марк')->searchable(),
                TextColumn::make('model')->label('Загвар')->searchable(),
                TextColumn::make('year')->label('Он')->placeholder('—'),
                TextColumn::make('accesses.customer.display_name')->label('Холбогдсон үйлчлүүлэгч')->listWithLineBreaks()->limitList(2),
                TextColumn::make('work_orders_count')->label('Ажил')->counts('workOrders'),
            ])
            ->recordActions([ViewAction::make()->label('Нээх')])
            ->emptyStateHeading('Машин алга')
            ->emptyStateDescription('Машиныг үйлчлүүлэгчийн хуудаснаас бүртгэж холбоно.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('current_plate_display')->label('Дугаар')->placeholder('—'),
                TextEntry::make('make')->label('Марк'),
                TextEntry::make('model')->label('Загвар')->placeholder('—'),
                TextEntry::make('year')->label('Он')->placeholder('—'),
                TextEntry::make('vin')->label('VIN')->placeholder('—')->formatStateUsing(fn (?string $state): string => $state ? '…'.substr($state, -6) : '—'),
            ]),
            View::make('filament.vehicle-detail')->columnSpanFull()->viewData(fn (Vehicle $record): array => ['vehicle' => $record]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'view' => ViewVehicle::route('/{record}'),
        ];
    }
}
