<?php

namespace App\Filament\Resources\OpeningRules;

use App\Enums\StaffRole;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\OpeningRules\Pages\ManageOpeningRules;
use App\Models\OpeningRule;
use App\Support\BusinessTime;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Shop opening windows per weekday (several windows per day model a lunch break). Times are UB local.
 */
class OpeningRuleResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = OpeningRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Тохиргоо';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'ажлын цаг';

    protected static ?string $pluralModelLabel = 'Ажлын цаг';

    protected static function writeRoles(): array
    {
        return [StaffRole::Owner];
    }

    public static function canDelete(Model $record): bool
    {
        return static::userHas([StaffRole::Owner]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('weekday')->label('Гараг')->options(collect(range(1, 7))->mapWithKeys(fn (int $day): array => [$day => BusinessTime::weekdayLabel($day)])->all())->required(),
            TimePicker::make('local_start')->label('Нээх')->seconds(false)->format('H:i')->required(),
            TimePicker::make('local_end')->label('Хаах')->seconds(false)->format('H:i')->required()->after('local_start'),
            DatePicker::make('active_from')->label('Хүчинтэй эхлэх')->native(false),
            DatePicker::make('active_until')->label('Хүчинтэй дуусах')->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('weekday')
            ->columns([
                TextColumn::make('weekday')->label('Гараг')->formatStateUsing(fn (int $state): string => BusinessTime::weekdayLabel($state)),
                TextColumn::make('local_start')->label('Нээх'),
                TextColumn::make('local_end')->label('Хаах'),
                TextColumn::make('active_from')->label('Эхлэх')->date('Y-m-d')->placeholder('—'),
                TextColumn::make('active_until')->label('Дуусах')->date('Y-m-d')->placeholder('—'),
                IconColumn::make('is_demo')->label('DEMO')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data): array => $data + ['is_demo' => false]),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Ажлын цаг тохируулаагүй — цаг товлох боломжгүй');
    }

    public static function getPages(): array
    {
        return ['index' => ManageOpeningRules::route('/')];
    }
}
