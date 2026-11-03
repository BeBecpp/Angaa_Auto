<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
use App\Support\BusinessTime;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ServiceRequestResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|UnitEnum|null $navigationGroup = 'Цагийн хуваарь';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'хүсэлт';

    protected static ?string $pluralModelLabel = 'Ирсэн хүсэлт';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ServiceRequest::query()->where('status', RequestStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label('Дугаар')->searchable()->weight('bold'),
                TextColumn::make('status')->label('Төлөв')->badge(),
                TextColumn::make('request_type')->label('Төрөл')->badge(),
                TextColumn::make('name')->label('Нэр')->searchable(),
                TextColumn::make('phone_display')->label('Утас')->searchable(query: fn (Builder $query, string $search): Builder => $query->where('phone_normalized', 'like', '%'.preg_replace('/\D/', '', $search).'%')),
                TextColumn::make('vehicle_text')->label('Машин')->description(fn (ServiceRequest $record): ?string => $record->plate_display)->searchable(['vehicle_text', 'plate_normalized']),
                TextColumn::make('service.name_mn')->label('Үйлчилгээ')->placeholder('Мэдэхгүй')->toggleable(),
                TextColumn::make('preferred_local_date')->label('Хүссэн өдөр')->date('Y-m-d')->description(fn (ServiceRequest $record): ?string => $record->preferred_window?->getLabel())->placeholder('—'),
                TextColumn::make('media_count')->counts('media')->label('Зураг'),
                TextColumn::make('created_at')->label('Ирсэн')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'm/d H:i'))->sortable(),
                TextColumn::make('assignedStaff.name')->label('Хариуцагч')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Төлөв')->options(RequestStatus::class)->default(RequestStatus::New->value),
                SelectFilter::make('request_type')->label('Төрөл')->options(RequestType::class),
            ])
            ->recordActions([ViewAction::make()->label('Нээх')])
            ->emptyStateHeading('Хүсэлт алга')
            ->emptyStateDescription('Шүүлтүүрийг өөрчилж бусад төлөвийг харна уу.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Хүсэлт')->columnSpanFull()->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextEntry::make('reference')->label('Дугаар')->weight('bold'),
                    TextEntry::make('status')->label('Төлөв')->badge(),
                    TextEntry::make('request_type')->label('Төрөл')->badge(),
                    TextEntry::make('name')->label('Нэр'),
                    TextEntry::make('phone_display')->label('Утас (баталгаажаагүй)')
                        ->url(fn (ServiceRequest $record): string => 'tel:'.$record->phone_normalized),
                    TextEntry::make('created_at')->label('Ирсэн')->formatStateUsing(fn ($state): string => BusinessTime::format($state)),
                    TextEntry::make('vehicle_text')->label('Машин (хэрэглэгчийн бичсэн)'),
                    TextEntry::make('plate_display')->label('Улсын дугаар (баталгаажаагүй)')->placeholder('—'),
                    TextEntry::make('vehicle_year')->label('Он')->placeholder('—'),
                    TextEntry::make('service.name_mn')->label('Үйлчилгээ')->placeholder('Мэдэхгүй'),
                    TextEntry::make('preferred_local_date')->label('Хүссэн өдөр')->date('Y-m-d')->placeholder('—'),
                    TextEntry::make('preferred_window')->label('Хүссэн цаг')->placeholder('—'),
                    TextEntry::make('reminder_preference')->label('Сануулга хүссэн')->formatStateUsing(fn (bool $state): string => $state ? 'Тийм' : 'Үгүй'),
                    TextEntry::make('callout_address')->label('Очих хаяг')->placeholder('—')->visible(fn (ServiceRequest $record): bool => $record->request_type === RequestType::Callout),
                    TextEntry::make('first_responded_at')->label('Анх холбогдсон')->formatStateUsing(fn ($state): string => BusinessTime::format($state))->placeholder('Холбогдоогүй'),
                ]),
                TextEntry::make('symptoms')->label('Асуудал')->columnSpanFull(),
                TextEntry::make('staff_notes')->label('Ажилтны тэмдэглэл')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Зураг')->columnSpanFull()->schema([
                View::make('filament.media-grid')->viewData(fn (ServiceRequest $record): array => ['media' => $record->media]),
            ]),
            Section::make('Хөрвүүлэлт')->columnSpanFull()->visible(fn (ServiceRequest $record): bool => $record->converted_at !== null)->schema([
                TextEntry::make('customer.display_name')->label('Үйлчлүүлэгч'),
                TextEntry::make('convertedAppointment.planned_start')->label('Баталгаажсан цаг')->formatStateUsing(fn ($state): string => BusinessTime::format($state)),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'view' => ViewServiceRequest::route('/{record}'),
        ];
    }
}
