<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CustomerResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Үйлчлүүлэгч';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'үйлчлүүлэгч';

    protected static ?string $pluralModelLabel = 'Үйлчлүүлэгч';

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('display_name')->label('Нэр')->required()->maxLength(120),
                TextInput::make('contact_phone')->label('Утас')->tel()->helperText('8 оронтой. Дараа нь солих бол «Утас солих» үйлдлийг ашиглана.'),
                Textarea::make('notes_internal')->label('Дотоод тэмдэглэл')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('display_name')->label('Нэр')->searchable()->weight('bold'),
                TextColumn::make('contact_phone')->label('Утас')->searchable(query: fn (Builder $query, string $search): Builder => $query->where('normalized_contact_phone', 'like', '%'.preg_replace('/\D/', '', $search).'%')),
                TextColumn::make('current_vehicles_count')->label('Машин')->counts('currentVehicles'),
                TextColumn::make('work_orders_count')->label('Ажил')->counts('workOrders'),
                IconColumn::make('user_id')->label('Портал')->boolean()->state(fn (Customer $record): bool => $record->user_id !== null),
                IconColumn::make('is_demo')->label('DEMO')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('archived')->label('Архивласан')->nullable()->attribute('archived_at')->default(false),
            ])
            ->recordActions([ViewAction::make()->label('Нээх')])
            ->emptyStateHeading('Үйлчлүүлэгч алга');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('display_name')->label('Нэр'),
                TextEntry::make('contact_phone')->label('Утас')->placeholder('—'),
                TextEntry::make('user.phone_verified_at')->label('Портал')->formatStateUsing(fn ($state): string => $state ? 'Холбогдсон, утас баталгаажсан' : '—')->placeholder('Холбогдоогүй'),
                TextEntry::make('notes_internal')->label('Дотоод тэмдэглэл')->placeholder('—')->columnSpanFull(),
            ]),
            View::make('filament.customer-detail')->columnSpanFull()->viewData(fn (Customer $record): array => ['customer' => $record]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
