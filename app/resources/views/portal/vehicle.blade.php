<x-layouts.portal :title="$vehicle->label()">
    <a href="{{ route('portal.home') }}" class="text-sm text-primary underline">← Нүүр</a>
    <h1 class="mt-3 flex flex-wrap items-center gap-3 text-[28px]"><x-plate-badge :plate="$vehicle->current_plate_display" /> {{ $vehicle->make }} {{ $vehicle->model }} {{ $vehicle->year }}</h1>

    @if ($reading)
        <p class="mt-2 text-muted">Сүүлд бүртгэсэн гүйлт: <span class="money font-semibold text-ink">{{ number_format($reading->km, 0, '.', ' ') }} км</span> ({{ \App\Support\BusinessTime::format($reading->observed_at, 'Y-m-d') }})</p>
    @endif

    @if ($plans->isNotEmpty())
        <section class="mt-6" aria-labelledby="plans-h">
            <h2 id="plans-h" class="text-xl">Дараагийн үйлчилгээ</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                @foreach ($plans as $plan)
                    @php($credible = $plan->due_km ? $evaluator->credibleReading($plan, $now) : null)
                    <div class="card">
                        <p class="font-semibold">{{ $plan->description }}</p>
                        <ul class="mt-2 space-y-1 text-sm text-muted">
                            @if ($plan->due_date)<li>Огноо: <span class="money text-ink">{{ $plan->due_date->format('Y-m-d') }}</span></li>@endif
                            @if ($plan->due_km)
                                <li>Км: <span class="money text-ink">{{ number_format($plan->due_km, 0, '.', ' ') }}</span>
                                    — @if ($credible) одоогийн {{ number_format($credible->km, 0, '.', ' ') }} км @else <span class="font-semibold">одоогийн км тодорхойгүй</span> @endif
                                </li>
                            @endif
                            <li>Аль түрүүлж хүрэх нь хамаарна.</li>
                        </ul>
                        <x-status-badge :status="$plan->status" class="mt-3" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8" aria-labelledby="history-h">
        <h2 id="history-h" class="text-xl">Засварын түүх</h2>
        @forelse ($orders as $order)
            <a href="{{ route('portal.job', $order) }}" class="card mt-3 block hover:border-primary">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold">{{ \App\Support\BusinessTime::format($order->received_at, 'Y-m-d') }} · {{ $order->order_number }}</span>
                    <x-status-badge :status="$order->status" />
                </div>
                <p class="mt-1 line-clamp-2 text-muted">{{ $order->complaint }}</p>
            </a>
        @empty
            <x-empty-state class="mt-3" title="Энэ машинд таны нэр дээрх засварын бүртгэл алга" />
        @endforelse
    </section>
</x-layouts.portal>
