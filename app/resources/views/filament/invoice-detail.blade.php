@php($money = fn (?int $amount): string => \App\Support\Money::format($amount))
<div style="display:grid;gap:16px;">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <x-filament::badge :color="$invoice->status->getColor()">{{ $invoice->status->getLabel() }}</x-filament::badge>
        @if ($invoice->status === \App\Enums\InvoiceStatus::Issued)
            <x-filament::badge :color="$invoice->paymentState()->getColor()">{{ $invoice->paymentState()->getLabel() }}</x-filament::badge>
        @endif
        <span>Ажил: {{ $invoice->workOrder->order_number }} · {{ $invoice->workOrder->customer_snapshot['display_name'] ?? '' }}</span>
        @if ($invoice->is_demo)<x-filament::badge color="warning">DEMO</x-filament::badge>@endif
    </div>
    <p style="font-size:12px;color:#58656C;">Энэ нь татварын баримт (ebarimt) биш. Нэхэмжилсэн дүн ба бодит хүлээн авсан мөнгийг тусад нь харуулна.</p>

    <table style="width:100%;font-size:14px;border-collapse:collapse;">
        <thead><tr style="text-align:left;color:#58656C;"><th>Төрөл</th><th>Тайлбар</th><th style="text-align:right">Тоо</th><th style="text-align:right">Нэгж</th><th style="text-align:right">Хөнг.</th><th style="text-align:right">Дүн</th></tr></thead>
        <tbody>
            @foreach ($invoice->lines->sortBy('sort_order') as $line)
                <tr style="border-top:1px solid #eee;">
                    <td>{{ $line->kind->getLabel() }}</td><td>{{ $line->description }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ \App\Support\Money::milliToDisplay($line->quantity_milli) }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $money($line->unit_price_mnt) }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $line->discount_mnt ? $money($line->discount_mnt) : '—' }}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $money($line->total_mnt) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <dl style="margin-left:auto;width:280px;font-variant-numeric:tabular-nums;display:grid;grid-template-columns:1fr auto;gap:4px;font-size:14px;">
        <dt>Ажлын хөлс</dt><dd style="text-align:right">{{ $money($invoice->labor_total_mnt) }}</dd>
        <dt>Сэлбэг</dt><dd style="text-align:right">{{ $money($invoice->parts_total_mnt) }}</dd>
        <dt style="font-weight:700;">Нийт нэхэмжилсэн</dt><dd style="text-align:right;font-weight:700;">{{ $money($invoice->grand_total_mnt) }}</dd>
        @if ($invoice->status === \App\Enums\InvoiceStatus::Issued)
            <dt>Хүлээн авсан (цэвэр)</dt><dd style="text-align:right">{{ $money($invoice->paidNetMnt()) }}</dd>
            <dt style="font-weight:700;">Үлдэгдэл</dt><dd style="text-align:right;font-weight:700;color:{{ $invoice->balanceMnt() > 0 ? '#B42318' : '#187047' }}">{{ $money($invoice->balanceMnt()) }}</dd>
        @endif
    </dl>

    <div>
        <h3 style="font-weight:600;">Төлбөрийн бүртгэл</h3>
        @forelse ($invoice->payments as $payment)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;">
                {{ \App\Support\BusinessTime::format($payment->received_at) }} · {{ $payment->method->getLabel() }} · <strong style="font-variant-numeric:tabular-nums">{{ $money($payment->amount_mnt) }}</strong>
                @if ($payment->reference) · {{ $payment->reference }}@endif
                <span style="font-size:12px;color:#58656C;">· бүртгэсэн {{ $payment->recorder?->name }}</span>
                @foreach ($payment->reversals as $reversal)
                    <br><span style="color:#B42318;font-size:13px;">Буцаалт {{ $money($reversal->amount_mnt) }} — {{ $reversal->reason }} ({{ \App\Support\BusinessTime::format($reversal->occurred_at) }})</span>
                @endforeach
            </div>
        @empty
            <p style="color:#58656C;">Төлбөр бүртгэгдээгүй.</p>
        @endforelse
    </div>
    @if ($invoice->void_reason)<p style="color:#B42318;">Хүчингүй болгосон шалтгаан: {{ $invoice->void_reason }}</p>@endif
    @if ($invoice->note)<p style="font-size:14px;">Тэмдэглэл: {{ $invoice->note }}</p>@endif
</div>
