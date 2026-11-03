<x-layouts.portal>
    <h1 class="text-[30px]">Сайн байна уу, {{ $customer->firstName() }}</h1>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        <section class="card" aria-labelledby="upcoming-h">
            <h2 id="upcoming-h" class="text-lg">Дараагийн цаг</h2>
            @if ($upcoming)
                <p class="mt-2 text-2xl font-bold money">{{ \App\Support\BusinessTime::format($upcoming->planned_start, 'm/d H:i') }}</p>
                <p class="text-muted">{{ $upcoming->service?->name_mn ?? 'Үйлчилгээ' }} · <x-status-badge :status="$upcoming->status" /></p>
                <a href="{{ route('portal.appointments') }}" class="btn-secondary mt-4">Цаг өөрчлөх / цуцлах хүсэлт</a>
            @else
                <p class="mt-2 text-muted">Баталгаажсан цаг алга.</p>
                <a href="{{ route('request.create') }}" class="btn-secondary mt-4">Шинэ хүсэлт өгөх</a>
            @endif
        </section>

        <section class="card" aria-labelledby="next-h">
            <h2 id="next-h" class="text-lg">Дараагийн үйлчилгээ</h2>
            @if ($nextPlan)
                <p class="mt-2 font-semibold">{{ $nextPlan->description }}</p>
                <p class="text-muted">
                    {{ $nextPlan->vehicle->label() }} —
                    @if ($nextPlan->due_date) {{ $nextPlan->due_date->format('Y-m-d') }} @endif
                    @if ($nextPlan->due_date && $nextPlan->due_km) эсвэл @endif
                    @if ($nextPlan->due_km) <span class="money">{{ number_format($nextPlan->due_km, 0, '.', ' ') }} км</span> @endif
                    (аль түрүүлж хүрэх нь)
                </p>
            @else
                <p class="mt-2 text-muted">Төлөвлөсөн үйлчилгээ алга.</p>
            @endif
        </section>
    </div>

    @if ($pendingEstimates->isNotEmpty())
        <section class="mt-6 rounded-[12px] border-2 border-accent bg-accent/10 p-5" aria-labelledby="pending-h">
            <h2 id="pending-h" class="text-lg">Таны зөвшөөрөл хүлээж буй үнийн санал</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($pendingEstimates as $order)
                    <li><a href="{{ route('portal.job', $order) }}" class="btn-accent">{{ $order->order_number }} — үнийн санал харах</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-8" aria-labelledby="active-h">
        <h2 id="active-h" class="text-2xl">Явагдаж буй ажил</h2>
        @forelse ($activeOrders as $order)
            <a href="{{ route('portal.job', $order) }}" class="card mt-3 flex flex-wrap items-center justify-between gap-3 hover:border-primary">
                <span><span class="font-semibold">{{ $order->order_number }}</span> · <x-plate-badge :plate="$order->vehicle_snapshot['plate'] ?? null" /> {{ $order->vehicle_snapshot['make'] ?? '' }} {{ $order->vehicle_snapshot['model'] ?? '' }}</span>
                <x-status-badge :status="$order->status" />
            </a>
        @empty
            <p class="mt-2 text-muted">Одоогоор засварт байгаа машин алга.</p>
        @endforelse
    </section>

    <section class="mt-8" aria-labelledby="vehicles-h">
        <h2 id="vehicles-h" class="text-2xl">Миний машинууд</h2>
        @if ($vehicles->isEmpty())
            <x-empty-state class="mt-3" title="Холбогдсон машин алга">Ажилтан таны машиныг баталгаажуулж холбосны дараа энд харагдана.</x-empty-state>
        @else
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($vehicles as $vehicle)
                    <a href="{{ route('portal.vehicle', $vehicle) }}" class="card hover:border-primary">
                        <x-plate-badge :plate="$vehicle->current_plate_display" />
                        <p class="mt-2 font-semibold">{{ $vehicle->make }} {{ $vehicle->model }} {{ $vehicle->year }}</p>
                        <p class="text-sm text-primary underline">Засварын түүх</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.portal>
