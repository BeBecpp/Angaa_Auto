@php($items = $order->inspectionItems()->with('checker')->orderBy('sort_order')->get())
<div style="margin-bottom:16px;">
    @forelse ($items as $item)
        <div style="border-top:1px solid #eee;padding:8px 0;">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <strong>{{ $item->label }}</strong>
                <x-filament::badge :color="$item->condition->getColor()">{{ $item->condition->getLabel() }}</x-filament::badge>
                @if ($item->published_at)<x-filament::badge color="primary">Хэрэглэгчид нийтэлсэн</x-filament::badge>@else<x-filament::badge color="gray">Ноорог</x-filament::badge>@endif
                <span style="font-size:12px;color:#58656C;">{{ $item->checker?->name }} · {{ \App\Support\BusinessTime::format($item->checked_at, 'm/d H:i') }}</span>
            </div>
            @if ($item->finding_internal)<p style="font-size:13px;"><span style="color:#58656C;">Дотоод:</span> {{ $item->finding_internal }}</p>@endif
            @if ($item->finding_customer)<p style="font-size:13px;"><span style="color:#58656C;">Хэрэглэгчид:</span> {{ $item->finding_customer }}</p>@endif
        </div>
    @empty
        <p style="color:#58656C;">Үзлэгийн мөр алга. Механикийн дэлгэцээс нэмнэ.</p>
    @endforelse
</div>
