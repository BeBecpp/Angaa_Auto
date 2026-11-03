<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Finance\InvoiceService;
use App\Actions\Finance\PaymentService;
use App\Enums\EstimateLineKind;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Concerns\RefreshesRecordAfterActions;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\Notify;
use App\Models\Payment;
use App\Support\BusinessTime;
use App\Support\EnumValue;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

/**
 * Manual payments only. A payment record states what staff received; it never claims gateway settlement.
 */
class ViewInvoice extends ViewRecord
{
    use RefreshesRecordAfterActions;

    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Нэхэмжлэл '.($this->record->invoice_number ?? '(ноорог)');
    }

    protected function getHeaderActions(): array
    {
        $draft = fn (): bool => $this->record->status === InvoiceStatus::Draft;
        $issued = fn (): bool => $this->record->status === InvoiceStatus::Issued;

        return [
            Action::make('editLines')->label('Мөр засах')->icon('heroicon-o-pencil-square')->color('gray')->visible($draft)
                ->modalWidth(Width::FiveExtraLarge)
                ->fillForm(fn (): array => [
                    'note' => $this->record->note,
                    'lines' => $this->record->lines()->orderBy('sort_order')->get()->map(fn ($line): array => [
                        'kind' => $line->kind->value, 'description' => $line->description, 'quantity' => Money::milliToDisplay($line->quantity_milli),
                        'unit_price_mnt' => $line->unit_price_mnt, 'discount_mnt' => $line->discount_mnt,
                    ])->all(),
                ])
                ->schema([
                    Repeater::make('lines')->label('Мөрүүд')->schema([
                        Select::make('kind')->label('Төрөл')->options(EstimateLineKind::class)->required(),
                        TextInput::make('description')->label('Тайлбар')->required()->columnSpan(2),
                        TextInput::make('quantity')->label('Тоо')->required()->regex('/^\d+([.,]\d{1,3})?$/'),
                        TextInput::make('unit_price_mnt')->label('Нэгж үнэ ₮')->integer()->minValue(0)->required(),
                        TextInput::make('discount_mnt')->label('Хөнгөлөлт ₮')->integer()->minValue(0)->default(0),
                    ])->columns(6)->minItems(1),
                    TextInput::make('note')->label('Тэмдэглэл'),
                ])
                ->modalDescription('Зөвшөөрсөн үнийн саналаас ялгаатай бол шалтгааныг тэмдэглэлд бичнэ үү.')
                ->action(fn (array $data) => Notify::attempt(fn () => app(InvoiceService::class)->saveDraftLines($this->record, array_values($data['lines']), auth()->user(), $data['note'] ?? null), 'Хадгалагдлаа')),

            Action::make('issue')->label('Нэхэмжлэл гаргах')->icon('heroicon-o-check')->visible($draft)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Нийт '.Money::format($this->record->grand_total_mnt).'. Гаргасны дараа мөр, дүн өөрчлөгдөхгүй.')
                ->action(fn () => Notify::attempt(fn () => app(InvoiceService::class)->issue($this->record, auth()->user()), 'Нэхэмжлэл гарлаа')),

            Action::make('pay')->label('Төлбөр бүртгэх')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn (): bool => $issued() && $this->record->balanceMnt() > 0)
                ->fillForm(fn (): array => ['amount_mnt' => $this->record->balanceMnt(), 'method' => PaymentMethod::Cash->value, 'received_at' => now(), 'idempotency_key' => (string) Str::ulid()])
                ->schema([
                    Hidden::make('idempotency_key'),
                    TextInput::make('amount_mnt')->label('Дүн ₮')->integer()->minValue(1)->required()
                        ->helperText(fn (): string => 'Үлдэгдэл: '.Money::format($this->record->balanceMnt())),
                    Select::make('method')->label('Хэлбэр')->options(PaymentMethod::class)->required(),
                    TextInput::make('reference')->label('Гүйлгээний дугаар / лавлах'),
                    DateTimePicker::make('received_at')->label('Хүлээн авсан')->timezone(BusinessTime::zone())->seconds(false)->required(),
                    TextInput::make('note')->label('Тэмдэглэл'),
                ])
                ->modalDescription('Гараар бүртгэх төлбөр. Төлбөрийн системийн (QPay г.м.) баталгаа биш.')
                ->action(fn (array $data) => Notify::attempt(fn () => app(PaymentService::class)->record(
                    $this->record,
                    (int) $data['amount_mnt'],
                    PaymentMethod::from(EnumValue::of($data['method'])),
                    auth()->user(),
                    $data['idempotency_key'],
                    $data['reference'] ?? null,
                    isset($data['received_at']) ? CarbonImmutable::parse($data['received_at']) : null,
                    $data['note'] ?? null,
                ), 'Төлбөр бүртгэгдлээ')),

            Action::make('reverse')->label('Төлбөр буцаах')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn (): bool => auth()->user()->isOwner() && $this->record->payments()->exists())
                ->fillForm(fn (): array => ['idempotency_key' => (string) Str::ulid()])
                ->schema([
                    Hidden::make('idempotency_key'),
                    Select::make('payment_id')->label('Төлбөр')->required()
                        ->options(fn (): array => $this->record->payments()->get()->mapWithKeys(fn (Payment $payment): array => [
                            $payment->id => BusinessTime::format($payment->received_at).' · '.$payment->method->getLabel().' · '.Money::format($payment->amount_mnt).($payment->reversedMnt() ? ' (буцаасан '.Money::format($payment->reversedMnt()).')' : ''),
                        ])->all()),
                    TextInput::make('amount_mnt')->label('Буцаах дүн ₮')->integer()->minValue(1)->required(),
                    Textarea::make('reason')->label('Шалтгаан')->required(),
                ])
                ->action(fn (array $data) => Notify::attempt(fn () => app(PaymentService::class)->reverse(
                    $this->record->payments()->findOrFail($data['payment_id']),
                    (int) $data['amount_mnt'],
                    $data['reason'],
                    auth()->user(),
                    $data['idempotency_key'],
                ), 'Буцаалт бүртгэгдлээ')),

            Action::make('print')->label('Хэвлэх')->icon('heroicon-o-printer')->color('gray')->url(fn (): string => route('print.invoice', $this->record))->openUrlInNewTab(),
            Action::make('order')->label('Ажлын захиалга')->icon('heroicon-o-wrench-screwdriver')->color('gray')->url(fn (): string => WorkOrderResource::getUrl('view', ['record' => $this->record->workOrder])),

            Action::make('void')->label('Хүчингүй болгох')->icon('heroicon-o-no-symbol')->color('danger')
                ->visible(fn (): bool => auth()->user()->isOwner() && $this->record->status !== InvoiceStatus::Void)
                ->schema([Textarea::make('reason')->label('Шалтгаан')->required()])
                ->action(fn (array $data) => Notify::attempt(fn () => app(InvoiceService::class)->void($this->record, $data['reason'], auth()->user()), 'Хүчингүй болголоо')),
        ];
    }
}
