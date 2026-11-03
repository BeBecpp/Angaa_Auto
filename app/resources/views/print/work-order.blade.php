<x-layouts.print :title="'Засварын тайлан '.$order->order_number">
    <h1 class="mt-5 text-2xl">Засварын тайлан — {{ $order->order_number }}</h1>
    <table class="mt-3 w-full text-sm">
        <tr><td class="w-40 py-0.5 text-muted">Үйлчлүүлэгч</td><td>{{ $order->customer_snapshot['display_name'] ?? '' }}</td></tr>
        <tr><td class="py-0.5 text-muted">Машин</td><td>{{ $order->vehicle_snapshot['plate'] ?? '' }} {{ $order->vehicle_snapshot['make'] ?? '' }} {{ $order->vehicle_snapshot['model'] ?? '' }} {{ $order->vehicle_snapshot['year'] ?? '' }}</td></tr>
        <tr><td class="py-0.5 text-muted">Хүлээн авсан</td><td class="money">{{ \App\Support\BusinessTime::format($order->received_at) }} @if ($order->intake_km)· {{ number_format($order->intake_km, 0, '.', ' ') }} км @endif</td></tr>
        @if ($order->delivered_at)<tr><td class="py-0.5 text-muted">Олгосон</td><td class="money">{{ \App\Support\BusinessTime::format($order->delivered_at) }}</td></tr>@endif
        <tr><td class="py-0.5 text-muted">Төлөв</td><td>{{ $order->status->getLabel() }}</td></tr>
    </table>

    <h2 class="mt-5 text-lg">Гомдол</h2>
    <p class="whitespace-pre-line text-sm">{{ $order->complaint }}</p>

    @if ($findings->isNotEmpty())
        <h2 class="mt-5 text-lg">Үзлэгийн дүгнэлт</h2>
        <table class="mt-1 w-full text-sm">
            @foreach ($findings as $item)
                <tr class="border-t border-line"><td class="w-48 py-1 font-semibold">{{ $item->label }}</td><td class="w-28 py-1">{{ $item->condition->getLabel() }}</td><td class="py-1">{{ $item->finding_customer }}</td></tr>
            @endforeach
        </table>
    @endif

    @php($approved = $revisions->firstWhere('state', \App\Enums\EstimateState::Approved))
    @if ($approved)
        <h2 class="mt-5 text-lg">Зөвшөөрсөн үнийн санал</h2>
        <x-estimate-revision :revision="$approved" class="mt-2 border-0 p-0" />
    @endif

    @if ($order->repairItems->isNotEmpty())
        <h2 class="mt-5 text-lg">Хийсэн ажил</h2>
        <ul class="list-disc pl-5 text-sm">
            @foreach ($order->repairItems as $item)<li>{{ $item->description }}@if ($item->work_done) — {{ $item->work_done }}@endif</li>@endforeach
        </ul>
    @endif

    @if ($order->customer_summary)
        <h2 class="mt-5 text-lg">Тайлбар</h2>
        <p class="whitespace-pre-line text-sm">{{ $order->customer_summary }}</p>
    @endif

    @if ($invoice)
        <h2 class="mt-5 text-lg">Төлбөр</h2>
        <p class="text-sm money">Нэхэмжлэл {{ $invoice->invoice_number }}: нийт {{ \App\Support\Money::format($invoice->grand_total_mnt) }}, төлсөн {{ \App\Support\Money::format($invoice->paidNetMnt()) }}, үлдэгдэл {{ \App\Support\Money::format($invoice->balanceMnt()) }} ({{ $invoice->paymentState()->getLabel() }})</p>
    @endif

    @if ($plans->isNotEmpty())
        <h2 class="mt-5 text-lg">Дараагийн үйлчилгээ</h2>
        <ul class="list-disc pl-5 text-sm">
            @foreach ($plans as $plan)<li>{{ $plan->description }}: @if ($plan->due_date){{ $plan->due_date->format('Y-m-d') }}@endif @if ($plan->due_date && $plan->due_km) эсвэл @endif @if ($plan->due_km){{ number_format($plan->due_km, 0, '.', ' ') }} км@endif (аль түрүүлж)</li>@endforeach
        </ul>
    @endif

    @if ($order->warranty_terms)<p class="mt-4 text-sm"><span class="font-semibold">Баталгааны нөхцөл:</span> {{ $order->warranty_terms }}</p>@endif
    <p class="mt-8 text-xs text-muted">{{ config('angaa.brand.name') }} · {{ config('angaa.brand.tagline') }}</p>
</x-layouts.print>
