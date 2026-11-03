@props(['service'])
<article class="card flex h-full flex-col">
    <h3 class="text-xl"><a href="{{ route('services.show', $service->slug) }}" class="hover:text-primary">{{ $service->name_mn }}</a></h3>
    @if ($service->summary)
        <p class="mt-2 flex-1 text-muted">{{ $service->summary }}</p>
    @else
        <div class="flex-1"></div>
    @endif
    <dl class="mt-4 space-y-1 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-muted">Үнэ</dt><dd><x-price-label :service="$service" /></dd></div>
        @if ($service->estimated_duration_min)
            <div class="flex justify-between gap-3"><dt class="text-muted">Барагцаа хугацаа</dt><dd class="money">~{{ $service->estimated_duration_min }} мин</dd></div>
        @endif
    </dl>
    <a href="{{ route('request.create', ['service' => $service->slug]) }}" class="btn-primary mt-5">Энэ үйлчилгээгээр хүсэлт өгөх</a>
</article>
