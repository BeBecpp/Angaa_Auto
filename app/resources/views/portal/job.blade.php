<x-layouts.portal :title="$order->order_number">
    <a href="{{ route('portal.vehicle', $order->vehicle) }}" class="text-sm text-primary underline">← Машины түүх</a>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-[28px]">{{ $order->order_number }}</h1>
        <div class="flex items-center gap-3">
            <x-status-badge :status="$order->status" />
            <a href="{{ route('portal.job.print', $order) }}" class="btn-secondary" target="_blank">Хэвлэх / PDF</a>
        </div>
    </div>
    <p class="mt-1 text-muted">
        <x-plate-badge :plate="$order->vehicle_snapshot['plate'] ?? null" /> {{ $order->vehicle_snapshot['make'] ?? '' }} {{ $order->vehicle_snapshot['model'] ?? '' }}
        · Хүлээн авсан {{ \App\Support\BusinessTime::format($order->received_at) }}
        @if ($order->intake_km) · <span class="money">{{ number_format($order->intake_km, 0, '.', ' ') }} км</span>@endif
    </p>

    @php($pending = $revisions->firstWhere('state', \App\Enums\EstimateState::Sent))
    @if ($pending)
        <section class="mt-6" aria-labelledby="approve-h">
            <h2 id="approve-h" class="sr-only">Зөвшөөрөл хүлээж буй үнийн санал</h2>
            <livewire:portal.estimate-panel :revision="$pending" :key="'estimate-'.$pending->id" />
        </section>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="min-w-0 space-y-6">
            <section class="card" aria-labelledby="complaint-h">
                <h2 id="complaint-h" class="text-lg">Таны хэлсэн асуудал</h2>
                <p class="mt-2 whitespace-pre-line">{{ $order->complaint }}</p>
            </section>

            <section class="card" aria-labelledby="findings-h">
                <h2 id="findings-h" class="text-lg">Үзлэгийн дүгнэлт</h2>
                @forelse ($findings as $item)
                    <div class="mt-3 border-t border-line pt-3 first:border-0 first:pt-0">
                        <div class="flex flex-wrap items-center gap-2"><span class="font-semibold">{{ $item->label }}</span> <x-status-badge :status="$item->condition" /></div>
                        <p class="mt-1 text-muted">{{ $item->finding_customer }}</p>
                    </div>
                @empty
                    <p class="mt-2 text-muted">Үзлэгийн дүгнэлт хараахан нийтлэгдээгүй.</p>
                @endforelse
            </section>

            <section class="card" aria-labelledby="photos-h">
                <h2 id="photos-h" class="text-lg">Зураг</h2>
                @if ($photos->isEmpty())
                    <p class="mt-2 text-muted">Танд харуулах зураг алга.</p>
                @else
                    @foreach ($photos->groupBy(fn ($photo) => $photo->category->getLabel()) as $category => $group)
                        <h3 class="mt-4 text-base font-semibold">{{ $category }}</h3>
                        <ul class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($group as $photo)
                                <li>
                                    <a href="{{ route('media.show', [$photo, 'full']) }}" target="_blank">
                                        <img src="{{ route('media.show', [$photo, 'thumb']) }}" alt="{{ $photo->caption ?: $category }}" loading="lazy" width="240" height="180" class="aspect-[4/3] w-full rounded-[8px] border border-line object-cover">
                                    </a>
                                    @if ($photo->caption)<p class="mt-1 text-sm text-muted">{{ $photo->caption }}</p>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                @endif
            </section>

            <section class="card" aria-labelledby="estimates-h">
                <h2 id="estimates-h" class="text-lg">Үнийн саналын хувилбарууд</h2>
                @forelse ($revisions as $revision)
                    <x-estimate-revision :revision="$revision" class="mt-4" />
                @empty
                    <p class="mt-2 text-muted">Үнийн санал хараахан гараагүй.</p>
                @endforelse
            </section>

            @if ($order->repairItems->isNotEmpty())
                <section class="card" aria-labelledby="work-h">
                    <h2 id="work-h" class="text-lg">Хийсэн ажил</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($order->repairItems as $item)
                            <li class="border-t border-line pt-2 first:border-0 first:pt-0"><span class="font-semibold">{{ $item->description }}</span>@if ($item->work_done)<span class="block text-muted">{{ $item->work_done }}</span>@endif</li>
                        @endforeach
                    </ul>
                    @if ($order->customer_summary)<p class="mt-4 whitespace-pre-line rounded-[8px] bg-canvas p-3">{{ $order->customer_summary }}</p>@endif
                    @if ($order->warranty_terms)<p class="mt-2 text-sm"><span class="font-semibold">Баталгаа:</span> {{ $order->warranty_terms }}</p>@endif
                </section>
            @endif
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="card" aria-labelledby="timeline-h">
                <h2 id="timeline-h" class="text-lg">Явц</h2>
                <x-timeline :events="$timeline" />
            </section>

            <section class="card" aria-labelledby="invoice-h">
                <h2 id="invoice-h" class="text-lg">Төлбөр</h2>
                @if ($invoice)
                    <dl class="mt-2 space-y-1">
                        <div class="flex justify-between"><dt class="text-muted">Нэхэмжлэл</dt><dd class="money">{{ $invoice->invoice_number }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Нийт</dt><dd><x-money :amount="$invoice->grand_total_mnt" /></dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Төлсөн</dt><dd><x-money :amount="$invoice->paidNetMnt()" /></dd></div>
                        <div class="flex justify-between font-semibold"><dt>Үлдэгдэл</dt><dd><x-money :amount="$invoice->balanceMnt()" /></dd></div>
                    </dl>
                    <x-status-badge :status="$invoice->paymentState()" class="mt-3" />
                @else
                    <p class="mt-2 text-muted">Нэхэмжлэл хараахан гараагүй.</p>
                @endif
            </section>

            @if ($plans->isNotEmpty())
                <section class="card" aria-labelledby="plan-h">
                    <h2 id="plan-h" class="text-lg">Дараагийн үйлчилгээ</h2>
                    @foreach ($plans as $plan)
                        <p class="mt-2 font-semibold">{{ $plan->description }}</p>
                        <p class="text-sm text-muted">@if ($plan->due_date){{ $plan->due_date->format('Y-m-d') }}@endif @if ($plan->due_date && $plan->due_km) эсвэл @endif @if ($plan->due_km){{ number_format($plan->due_km, 0, '.', ' ') }} км@endif</p>
                    @endforeach
                </section>
            @endif

            @if ($order->declined_recommendations)
                <section class="card" aria-labelledby="declined-h">
                    <h2 id="declined-h" class="text-lg">Татгалзсан зөвлөмж</h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-muted">{{ $order->declined_recommendations }}</p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.portal>
