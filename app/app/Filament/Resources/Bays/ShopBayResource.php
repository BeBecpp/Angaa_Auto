<?php

namespace App\Filament\Resources\Bays;

use App\Enums\StaffRole;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Bays\Pages\ManageShopBays;
use App\Models\ShopBay;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShopBayResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = ShopBay::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|UnitEnum|null $navigationGroup = 'Тохиргоо';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'пост';

    protected static ?string $pluralModelLabel = 'Пост';

    protected static ?string $slug = 'bays';

    protected static function writeRoles(): array
    {
        return [StaffRole::Owner];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Нэр')->required()->maxLength(64),
            TagsInput::make('supported_categories')->label('Тохирох ангилал'),
            TextInput::make('sort_order')->label('Дараалал')->integer()->default(0),
            Toggle::make('active')->label('Идэвхтэй (засварт байгаа бол унтраана)')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Нэр')->weight('bold'),
                TextColumn::make('supported_categories')->label('Ангилал')->badge()->placeholder('—'),
                IconColumn::make('active')->label('Идэвхтэй')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageShopBays::route('/')];
    }
}
