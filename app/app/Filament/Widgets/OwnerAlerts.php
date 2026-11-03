<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\OutboxStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Invoice;
use App\Models\NotificationOutbox;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\ResourceReservation;
use App\Models\SystemHeartbeat;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use Filament\Widgets\Widget;

/**
 * Alerts plus a clear split between billed revenue and money actually received.
 */
class OwnerAlerts extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.owner-alerts';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isOfficeStaff() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monthStart = BusinessTime::now()->startOfMonth()->utc();
        $todayStart = BusinessTime::today()->utc();

        $issued = Invoice::query()->where('status', InvoiceStatus::Issued);
        $unpaid = (clone $issued)->get()->filter(fn (Invoice $invoice): bool => $invoice->balanceMnt() > 0);

        $paymentsBetween = fn ($from): int => (int) Payment::query()->where('status', 'settled')->where('received_at', '>=', $from)->sum('amount_mnt')
            - (int) PaymentReversal::query()->where('occurred_at', '>=', $from)->sum('amount_mnt');

        return [
            'alerts' => [
                ['label' => 'Зөвшөөрөл хүлээж буй', 'count' => WorkOrder::query()->where('status', WorkOrderStatus::AwaitingApproval)->count(), 'tone' => 'warning'],
                ['label' => 'Сэлбэг хүлээж буй', 'count' => WorkOrder::query()->where('status', WorkOrderStatus::WaitingParts)->count(), 'tone' => 'warning'],
                ['label' => 'Амласан хугацаа хэтэрсэн', 'count' => WorkOrder::query()->where('promised_ready_at', '<', now())->whereNotIn('status', [WorkOrderStatus::ReadyForPickup, WorkOrderStatus::Delivered, WorkOrderStatus::Cancelled])->count(), 'tone' => 'danger'],
                ['label' => 'Пост хугацаа хэтэрсэн (давхцал шалгах)', 'count' => ResourceReservation::query()->whereNotNull('actual_occupied_from')->whereNull('actual_occupied_to')->where('ends_at', '<', now())->count(), 'tone' => 'danger'],
                ['label' => 'Амжилтгүй / тодорхойгүй мессеж', 'count' => NotificationOutbox::query()->whereIn('status', [OutboxStatus::Failed, OutboxStatus::Unknown])->count(), 'tone' => 'danger'],
                ['label' => 'Үлдэгдэлтэй нэхэмжлэл', 'count' => $unpaid->count(), 'tone' => 'danger'],
            ],
            'outstanding' => $unpaid->sum(fn (Invoice $invoice): int => $invoice->balanceMnt()),
            'billedToday' => (int) (clone $issued)->where('issued_at', '>=', $todayStart)->sum('grand_total_mnt'),
            'billedMonth' => (int) (clone $issued)->where('issued_at', '>=', $monthStart)->sum('grand_total_mnt'),
            'receivedToday' => $paymentsBetween($todayStart),
            'receivedMonth' => $paymentsBetween($monthStart),
            'scheduler' => SystemHeartbeat::query()->find('scheduler')?->beat_at,
            'worker' => SystemHeartbeat::query()->find('queue')?->beat_at,
        ];
    }
}
