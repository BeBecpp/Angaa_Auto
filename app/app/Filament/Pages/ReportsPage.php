<?php

namespace App\Filament\Pages;

use App\Enums\StaffRole;
use App\Models\Invoice;
use App\Support\Audit;
use App\Support\BusinessTime;
use App\Support\Reports;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class ReportsPage extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Тойм';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Тайлан';

    protected static ?string $title = 'Тайлан';

    protected static ?string $slug = 'reports';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(StaffRole::Owner, StaffRole::Advisor) ?? false;
    }

    public function mount(): void
    {
        $this->from = BusinessTime::now()->startOfMonth()->toDateString();
        $this->to = BusinessTime::today()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Нэхэмжлэл CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn (): bool => auth()->user()->isOwner())
                ->action(fn (): StreamedResponse => $this->exportInvoices()),
        ];
    }

    public function exportInvoices(): StreamedResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $from = BusinessTime::fromLocal($this->from.' 00:00');
        $to = BusinessTime::fromLocal($this->to.' 00:00')->addDay();
        Audit::record('report.invoice_export', null, ['from' => $this->from, 'to' => $this->to]);

        return response()->streamDownload(function () use ($from, $to): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Дугаар', 'Огноо', 'Ажил', 'Үйлчлүүлэгч', 'Нийт', 'Төлсөн', 'Үлдэгдэл', 'Төлөв']);

            Invoice::query()->with('workOrder')->whereNotNull('issued_at')->whereBetween('issued_at', [$from, $to])->orderBy('issued_at')
                ->each(function (Invoice $invoice) use ($out): void {
                    fputcsv($out, array_map([Reports::class, 'csvSafe'], [
                        $invoice->invoice_number,
                        BusinessTime::format($invoice->issued_at, 'Y-m-d'),
                        $invoice->workOrder->order_number,
                        $invoice->workOrder->customer_snapshot['display_name'] ?? '',
                        $invoice->grand_total_mnt,
                        $invoice->paidNetMnt(),
                        $invoice->balanceMnt(),
                        $invoice->status->getLabel(),
                    ]));
                });

            fclose($out);
        }, 'invoices-'.$this->from.'_'.$this->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to) && $this->from <= $this->to;

        return ['report' => $valid ? Reports::build($this->from, $this->to) : null];
    }
}
