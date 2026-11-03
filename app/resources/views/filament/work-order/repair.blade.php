@php
    $repairs = $order->repairItems()->with('completer')->latest('completed_at')->get();
    $checks = $order->qcChecks()->with('checker')->latest('checked_at')->get();
    $canSeeCost = auth()->user()?->isOfficeStaff();
@endphp
<div style="display:grid;gap:16px;">
    <div>
        <h3 style="font-weight:600;">Хийсэн ажил</h3>
        @forelse ($repairs as $item)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;">
                <strong>{{ $item->description }}</strong> × {{ \App\Support\Money::milliToDisplay($item->quantity_milli) }}
                @if ($item->work_done)<br>{{ $item->work_done }}@endif
                <br><span style="font-size:12px;color:#58656C;">{{ $item->completer?->name }} · {{ \App\Support\BusinessTime::format($item->completed_at) }}
                @if ($canSeeCost && $item->cost_mnt !== null) · өртөг {{ \App\Support\Money::format($item->cost_mnt) }}@endif
                @if (! $item->estimate_line_id) · <strong>эзний онцгой зөвшөөрлөөр</strong>@endif</span>
            </div>
        @empty
            <p style="color:#58656C;">Бүртгэсэн ажил алга.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;">Чанарын шалгалт</h3>
        @forelse ($checks as $check)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <x-filament::badge :color="$check->result->getColor()">{{ $check->result->getLabel() }}</x-filament::badge>
                {{ $check->item }} @if ($check->note)— {{ $check->note }}@endif
                <span style="font-size:12px;color:#58656C;">{{ $check->checker?->name }} · {{ \App\Support\BusinessTime::format($check->checked_at, 'm/d H:i') }}</span>
            </div>
        @empty
            <p style="color:#58656C;">Чанарын шалгалт хийгдээгүй.</p>
        @endforelse
    </div>
    @if ($order->customer_summary)
        <div><h3 style="font-weight:600;">Хэрэглэгчид өгөх тайлбар</h3><p style="white-space:pre-line;font-size:14px;">{{ $order->customer_summary }}</p></div>
    @endif
</div>
