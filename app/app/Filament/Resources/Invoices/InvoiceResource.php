<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Support\BusinessTime;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InvoiceResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Санхүү';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'нэхэмжлэл';

    protected static ?string $pluralModelLabel = 'Нэхэмжлэл';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('Дугаар')->placeholder('Ноорог')->searchable()->weight('bold'),
                TextColumn::make('status')->label('Төлөв')->badge(),
                TextColumn::make('workOrder.order_number')->label('Ажил')->searchable(),
                TextColumn::make('workOrder.customer.display_name')->label('Үйлчлүүлэгч'),
                TextColumn::make('grand_total_mnt')->label('Нийт')->formatStateUsing(fn (int $state): string => Money::format($state))->alignEnd(),
                TextColumn::make('paid')->label('Төлсөн')->state(fn (Invoice $record): string => Money::format($record->paidNetMnt()))->alignEnd(),
                TextColumn::make('balance')->label('Үлдэгдэл')->state(fn (Invoice $record): string => Money::format($record->balanceMnt()))->alignEnd()
                    ->color(fn (Invoice $record): ?string => $record->balanceMnt() > 0 ? 'danger' : null),
                TextColumn::make('payment_state')->label('Төлбөр')->badge()->state(fn (Invoice $record) => $record->status === InvoiceStatus::Issued ? $record->paymentState() : null)->placeholder('—'),
                TextColumn::make('issued_at')->label('Гаргасан')->formatStateUsing(fn ($state): string => BusinessTime::format($state, 'Y-m-d'))->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Төлөв')->options(InvoiceStatus::class),
            ])
            ->recordActions([ViewAction::make()->label('Нээх')])
            ->emptyStateHeading('Нэхэмжлэл алга')
            ->emptyStateDescription('Нэхэмжлэлийг ажлын захиалгаас зөвшөөрсөн үнийн саналаар үүсгэнэ.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.invoice-detail')->columnSpanFull()->viewData(fn (Invoice $record): array => ['invoice' => $record->load(['lines', 'payments.reversals', 'payments.recorder', 'workOrder'])]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
