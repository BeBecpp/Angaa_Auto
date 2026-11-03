<?php

namespace App\Filament\Resources\Closures;

use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Closures\Pages\ManageClosures;
use App\Filament\Support\ScheduleFields;
use App\Models\Closure;
use App\Models\ShopBay;
use App\Models\User;
use App\Support\BusinessTime;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Holidays, shop closures, bay maintenance and mechanic leave. Blocks new reservations in the window.
 */
class ClosureResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = Closure::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|UnitEnum|null $navigationGroup = 'Тохиргоо';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'хаалт / чөлөө';

    protected static ?string $pluralModelLabel = 'Амралт, хаалт, чөлөө';

    public static function canDelete(Model $record): bool
    {
        return static::userHas(static::writeRoles());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('scope')->label('Хамрах хүрээ')->options(['shop' => 'Бүх сервис', 'bay' => 'Пост', 'mechanic' => 'Механик (чөлөө)'])->default('shop')->required()->live(),
            Select::make('scope_id')->label('Аль нь')
                ->options(fn (Get $get): array => match ($get('scope')) {
                    'bay' => ShopBay::query()->pluck('name', 'id')->all(),
                    'mechanic' => ScheduleFields::mechanics(),
                    default => [],
                })
                ->visible(fn (Get $get): bool => in_array($get('scope'), ['bay', 'mechanic'], true))
                ->required(fn (Get $get): bool => in_array($get('scope'), ['bay', 'mechanic'], true)),
            DateTimePicker::make('starts_at')->label('Эхлэх')->timezone(BusinessTime::zone())->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Дуусах')->timezone(BusinessTime::zone())->seconds(false)->required()->after('starts_at'),
            TextInput::make('reason')->label('Шалтгаан (нийтэд харагдана)')->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('scope')->label('Хүрээ')->formatStateUsing(fn (string $state, Closure $record): string => match ($state) {
                    'bay' => 'Пост: '.(ShopBay::query()->find($record->scope_id)?->name ?? '?'),
                    'mechanic' => 'Механик: '.(User::query()->find($record->scope_id)?->name ?? '?'),
                    default => 'Бүх сервис',
                }),
                TextColumn::make('starts_at')->label('Эхлэх')->formatStateUsing(fn ($state): string => BusinessTime::format($state)),
                TextColumn::make('ends_at')->label('Дуусах')->formatStateUsing(fn ($state): string => BusinessTime::format($state)),
                TextColumn::make('reason')->label('Шалтгаан'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageClosures::route('/')];
    }
}
