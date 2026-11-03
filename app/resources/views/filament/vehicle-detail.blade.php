@php
    $accesses = $vehicle->accesses()->with('customer')->latest('starts_at')->get();
    $orders = $vehicle->workOrders()->with('customer')->latest('received_at')->get();
    $readings = $vehicle->odometerReadings()->with('recorder')->latest('observed_at')->limit(15)->get();
    $duplicates = app(\App\Actions\Customers\CustomerService::class)->possibleDuplicates($vehicle->current_plate_display, $vehicle->vin)->where('id', '!=', $vehicle->id);
@endphp
<div style="display:grid;gap:18px;">
    @if ($duplicates->isNotEmpty())
        <p style="background:#FDEDEB;color:#B42318;padding:8px;border-radius:8px;font-size:14px;">Ижил дугаар/VIN-тэй өөр бүртгэл байна (хянах): {{ $duplicates->map->label()->join(', ') }}. Автоматаар нэгтгэхгүй.</p>
    @endif
    <div>
        <h3 style="font-weight:600;">Холбогдсон үйлчлүүлэгч</h3>
        @foreach ($accesses as $access)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;">
                <a style="color:#0B5A60;text-decoration:underline;" href="{{ \App\Filament\Resources\Customers\CustomerResource::getUrl('view', ['record' => $access->customer]) }}">{{ $access->customer->display_name }}</a>
                · {{ $access->relationship->getLabel() }} · {{ \App\Support\BusinessTime::format($access->starts_at, 'Y-m-d') }}
                @if ($access->ends_at)<strong style="color:#B42318;"> — дууссан</strong>@endif
            </div>
        @endforeach
    </div>
    <div>
        <h3 style="font-weight:600;">Ажлын түүх (ажилтанд бүгд; үйлчлүүлэгчид зөвхөн өөрийнх нь)</h3>
        @forelse ($orders as $order)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <a style="color:#0B5A60;text-decoration:underline;" href="{{ \App\Filament\Resources\WorkOrders\WorkOrderResource::getUrl('view', ['record' => $order]) }}">{{ $order->order_number }}</a>
                <x-filament::badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-filament::badge>
                <span>{{ $order->customer->display_name }}</span>
                <span style="color:#58656C;">{{ \App\Support\BusinessTime::format($order->received_at, 'Y-m-d') }}</span>
            </div>
        @empty
            <p style="color:#58656C;">Ажил алга.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;">Км бүртгэл (өмнөх бүртгэл устахгүй)</h3>
        @forelse ($readings as $reading)
            <div style="border-top:1px solid #eee;padding:4px 0;font-size:14px;font-variant-numeric:tabular-nums;">
                {{ number_format($reading->km, 0, '.', ' ') }} км · {{ \App\Support\BusinessTime::format($reading->observed_at) }} · {{ $reading->recorder?->name }}
                @if ($reading->correction_of_id)<strong style="color:#B42318;"> залруулга: {{ $reading->note }}</strong>@endif
            </div>
        @empty
            <p style="color:#58656C;">Км бүртгэгдээгүй — км-ээр сануулга ажиллахгүй (тодорхойгүй).</p>
        @endforelse
    </div>
</div>
