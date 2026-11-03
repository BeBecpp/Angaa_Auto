@php
    $invoices = $order->invoices()->latest('id')->get();
    $plans = \App\Models\MaintenancePlan::query()->where('vehicle_id', $order->vehicle_id)->where('customer_id', $order->customer_id)->latest()->get();
@endphp
<div style="display:grid;gap:16px;">
    <div>
        <h3 style="font-weight:600;">Нэхэмжлэл</h3>
        @forelse ($invoices as $invoice)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <a href="{{ \App\Filament\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $invoice]) }}" style="text-decoration:underline;color:#0B5A60;">{{ $invoice->invoice_number ?? 'Ноорог #'.$invoice->id }}</a>
                <x-filament::badge :color="$invoice->status->getColor()">{{ $invoice->status->getLabel() }}</x-filament::badge>
                <span style="font-variant-numeric:tabular-nums">Нийт {{ \App\Support\Money::format($invoice->grand_total_mnt) }}</span>
                @if ($invoice->status === \App\Enums\InvoiceStatus::Issued)
                    <span style="font-variant-numeric:tabular-nums">· Үлдэгдэл {{ \App\Support\Money::format($invoice->balanceMnt()) }}</span>
                    <x-filament::badge :color="$invoice->paymentState()->getColor()">{{ $invoice->paymentState()->getLabel() }}</x-filament::badge>
                @endif
            </div>
        @empty
            <p style="color:#58656C;">Нэхэмжлэл алга.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;">Энэ машины дараагийн үйлчилгээний төлөвлөгөө</h3>
        @forelse ($plans as $plan)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <x-filament::badge :color="$plan->status->getColor()">{{ $plan->status->getLabel() }}</x-filament::badge>
                <strong>{{ $plan->description }}</strong>
                @if ($plan->due_date)<span>{{ $plan->due_date->format('Y-m-d') }}</span>@endif
                @if ($plan->due_km)<span style="font-variant-numeric:tabular-nums">{{ number_format($plan->due_km, 0, '.', ' ') }} км</span>@endif
            </div>
        @empty
            <p style="color:#58656C;">Төлөвлөгөө алга.</p>
        @endforelse
    </div>
</div>
