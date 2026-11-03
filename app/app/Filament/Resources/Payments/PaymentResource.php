<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Support\BusinessTime;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only payment journal. Payments are recorded and reversed from the invoice page.
 */
class PaymentResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Санхүү';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'төлбөр';

    protected static ?string $pluralModelLabel = 'Төлбөр';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('received_at')->label('Хүлээн авсан')->formatStateUsing(fn ($state): string => BusinessTime::format($state))->sortable(),
                TextColumn::make('invoice.invoice_number')->label('Нэхэмжлэл')->searchable(),
                TextColumn::make('method')->label('Хэлбэр')->badge()->color('gray'),
                TextColumn::make('amount_mnt')->label('Дүн')->formatStateUsing(fn (int $state): string => Money::format($state))->alignEnd(),
                TextColumn::make('reversed')->label('Буцаасан')->state(fn (Payment $record): string => $record->reversedMnt() ? Money::format($record->reversedMnt()) : '—')->alignEnd(),
                TextColumn::make('reference')->label('Лавлах')->placeholder('—')->searchable(),
                TextColumn::make('recorder.name')->label('Бүртгэсэн'),
            ])
            ->filters([SelectFilter::make('method')->label('Хэлбэр')->options(PaymentMethod::class)])
            ->recordActions([
                Action::make('invoice')->label('Нэхэмжлэл')->url(fn (Payment $record): string => InvoiceResource::getUrl('view', ['record' => $record->invoice])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayments::route('/')];
    }
}
