@props(['service'])
@php($type = $service->price_type)
<span {{ $attributes->merge(['class' => 'money font-semibold']) }}>
    @if ($type === \App\Enums\PriceType::InspectionRequired || $service->price_mnt === null)
        Үзлэгийн дараа үнийн санал
    @elseif ($type === \App\Enums\PriceType::From)
        {{ \App\Support\Money::format($service->price_mnt) }}-с эхэлнэ
    @else
        {{ \App\Support\Money::format($service->price_mnt) }}
    @endif
    @if ($service->is_demo)
        <span class="badge ml-1 bg-accent/30 text-xs text-ink">DEMO</span>
    @endif
</span>
