<?php

namespace App\Filament\Resources\Services;

use App\Enums\PriceType;
use App\Enums\StaffRole;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Service;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ServiceResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'Тохиргоо';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'үйлчилгээ';

    protected static ?string $pluralModelLabel = 'Үйлчилгээ';

    protected static function writeRoles(): array
    {
        return [StaffRole::Owner];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_mn')->label('Нэр')->required()->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set, ?Service $record) => $record ? null : $set('slug', Str::slug(Str::ascii((string) $state)))),
            TextInput::make('slug')->label('URL')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('category')->label('Ангилал'),
            Select::make('price_type')->label('Үнийн төрөл')->options(PriceType::class)->required(),
            TextInput::make('price_mnt')->label('Үнэ ₮ (тогтмол/эхлэх)')->integer()->minValue(0),
            TextInput::make('estimated_duration_min')->label('Барагцаа хугацаа (мин)')->integer()->minValue(0),
            TextInput::make('buffer_min')->label('Дараагийн ажлын өмнөх зай (мин)')->integer()->minValue(0)->default(0),
            TextInput::make('sort_order')->label('Дараалал')->integer()->default(0),
            Textarea::make('summary')->label('Товч')->columnSpanFull(),
            Textarea::make('description')->label('Дэлгэрэнгүй')->columnSpanFull(),
            Textarea::make('inclusions')->label('Багтсан'),
            Textarea::make('exclusions')->label('Багтаагүй'),
            Toggle::make('active')->label('Идэвхтэй')->default(true),
            Toggle::make('published')->label('Сайтад нийтлэх (бодитоор хийдэг үйлчилгээ)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name_mn')->label('Нэр')->searchable()->weight('bold'),
                TextColumn::make('category')->label('Ангилал')->placeholder('—'),
                TextColumn::make('price_type')->label('Үнийн төрөл')->badge(),
                TextColumn::make('price_mnt')->label('Үнэ')->formatStateUsing(fn (?int $state): string => Money::format($state))->placeholder('—'),
                IconColumn::make('published')->label('Нийтэлсэн')->boolean(),
                IconColumn::make('active')->label('Идэвхтэй')->boolean(),
                IconColumn::make('is_demo')->label('DEMO')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageServices::route('/')];
    }
}
