<x-layouts.print :title="'Нэхэмжлэл '.($invoice->invoice_number ?? 'ноорог')">
    @php($snapshot = $invoice->issue_snapshot ?? [])
    <div class="mt-5 flex items-start justify-between">
        <div>
            <h1 class="text-2xl">Нэхэмжлэл {{ $invoice->invoice_number ?? '(НООРОГ)' }}</h1>
            <p class="text-sm text-muted">Татварын баримт (ebarimt) биш.</p>
        </div>
        <p class="badge {{ $invoice->status === \App\Enums\InvoiceStatus::Void ? 'bg-danger-soft text-danger' : 'bg-primary-soft text-primary' }}">{{ $invoice->status->getLabel() }}</p>
    </div>
    <table class="mt-3 w-full text-sm">
        <tr><td class="w-40 py-0.5 text-muted">Ажлын дугаар</td><td>{{ $snapshot['order_number'] ?? $invoice->workOrder->order_number }}</td></tr>
        <tr><td class="py-0.5 text-muted">Үйлчлүүлэгч</td><td>{{ ($snapshot['customer'] ?? $invoice->workOrder->customer_snapshot)['display_name'] ?? '' }}</td></tr>
        @php($vehicle = $snapshot['vehicle'] ?? $invoice->workOrder->vehicle_snapshot)
        <tr><td class="py-0.5 text-muted">Машин</td><td>{{ $vehicle['plate'] ?? '' }} {{ $vehicle['make'] ?? '' }} {{ $vehicle['model'] ?? '' }}</td></tr>
        <tr><td class="py-0.5 text-muted">Огноо</td><td class="money">{{ \App\Support\BusinessTime::format($invoice->issued_at ?? $invoice->created_at, 'Y-m-d') }}</td></tr>
    </table>

    <table class="mt-5 w-full text-sm">
        <thead><tr class="border-b-2 border-ink text-left"><th class="py-1">Төрөл</th><th class="py-1">Тайлбар</th><th class="py-1 text-right">Тоо</th><th class="py-1 text-right">Нэгж үнэ</th><th class="py-1 text-right">Хөнгөлөлт</th><th class="py-1 text-right">Дүн</th></tr></thead>
        <tbody>
            @foreach ($invoice->lines->sortBy('sort_order') as $line)
                <tr class="border-b border-line">
                    <td class="py-1">{{ $line->kind->getLabel() }}</td>
                    <td class="py-1">{{ $line->description }}</td>
                    <td class="py-1 text-right money">{{ \App\Support\Money::milliToDisplay($line->quantity_milli) }}</td>
                    <td class="py-1 text-right money">{{ \App\Support\Money::format($line->unit_price_mnt) }}</td>
                    <td class="py-1 text-right money">{{ $line->discount_mnt ? \App\Support\Money::format($line->discount_mnt) : '—' }}</td>
                    <td class="py-1 text-right money">{{ \App\Support\Money::format($line->total_mnt) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="ml-auto mt-4 w-72 text-sm money">
        <tr><td class="py-0.5">Ажлын хөлс</td><td class="text-right">{{ \App\Support\Money::format($invoice->labor_total_mnt) }}</td></tr>
        <tr><td class="py-0.5">Сэлбэг</td><td class="text-right">{{ \App\Support\Money::format($invoice->parts_total_mnt) }}</td></tr>
        <tr class="border-t-2 border-ink text-base font-bold"><td class="py-1">Нийт</td><td class="text-right">{{ \App\Support\Money::format($invoice->grand_total_mnt) }}</td></tr>
        @if ($invoice->status === \App\Enums\InvoiceStatus::Issued)
            <tr><td class="py-0.5">Төлсөн</td><td class="text-right">{{ \App\Support\Money::format($invoice->paidNetMnt()) }}</td></tr>
            <tr class="font-semibold"><td class="py-0.5">Үлдэгдэл</td><td class="text-right">{{ \App\Support\Money::format($invoice->balanceMnt()) }}</td></tr>
        @endif
    </table>

    @if ($invoice->payments->isNotEmpty())
        <h2 class="mt-6 text-base font-semibold">Төлбөрийн бүртгэл</h2>
        <table class="mt-1 w-full text-sm">
            @foreach ($invoice->payments as $payment)
                <tr class="border-b border-line">
                    <td class="py-1 money">{{ \App\Support\BusinessTime::format($payment->received_at) }}</td>
                    <td class="py-1">{{ $payment->method->getLabel() }}</td>
                    <td class="py-1">{{ $payment->reference }}</td>
                    <td class="py-1 text-right money">{{ \App\Support\Money::format($payment->amount_mnt) }}@if ($payment->reversedMnt()) (буцаасан {{ \App\Support\Money::format($payment->reversedMnt()) }})@endif</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($footer = \App\Support\Settings::get('invoice_footer'))<p class="mt-6 text-sm">{{ $footer }}</p>@endif
</x-layouts.print>
