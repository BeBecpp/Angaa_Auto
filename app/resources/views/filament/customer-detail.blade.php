@php
    $accesses = $customer->vehicleAccesses()->with('vehicle')->latest('starts_at')->get();
    $orders = $customer->workOrders()->latest('received_at')->limit(20)->get();
    $consents = $customer->consents()->latest()->get();
    $plans = $customer->maintenancePlans()->with('vehicle')->latest()->limit(20)->get();
@endphp
<div style="display:grid;gap:18px;">
    <div>
        <h3 style="font-weight:600;">Машин</h3>
        @forelse ($accesses as $access)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;">
                <a style="color:#0B5A60;text-decoration:underline;" href="{{ \App\Filament\Resources\Vehicles\VehicleResource::getUrl('view', ['record' => $access->vehicle]) }}">{{ $access->vehicle->label() }}</a>
                · {{ $access->relationship->getLabel() }} · {{ \App\Support\BusinessTime::format($access->starts_at, 'Y-m-d') }}
                @if ($access->ends_at)<strong style="color:#B42318;"> — дууссан {{ \App\Support\BusinessTime::format($access->ends_at, 'Y-m-d') }}</strong>@endif
            </div>
        @empty
            <p style="color:#58656C;">Холбогдсон машин алга.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;">Ажлын түүх</h3>
        @forelse ($orders as $order)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <a style="color:#0B5A60;text-decoration:underline;" href="{{ \App\Filament\Resources\WorkOrders\WorkOrderResource::getUrl('view', ['record' => $order]) }}">{{ $order->order_number }}</a>
                <x-filament::badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-filament::badge>
                <span>{{ $order->vehicle_snapshot['plate'] ?? '' }}</span>
                <span style="color:#58656C;">{{ \App\Support\BusinessTime::format($order->received_at, 'Y-m-d') }}</span>
            </div>
        @empty
            <p style="color:#58656C;">Ажил алга.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;">Зөвшөөрөл (утсанд холбогдсон)</h3>
        @forelse ($consents as $consent)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;">
                {{ $consent->purpose->getLabel() }} · {{ \App\Support\Phone::mask($consent->phone_normalized) }} ·
                @if ($consent->revoked_at)<span style="color:#B42318;">цуцалсан {{ \App\Support\BusinessTime::format($consent->revoked_at, 'Y-m-d') }}</span>@else<span style="color:#187047;">идэвхтэй {{ \App\Support\BusinessTime::format($consent->granted_at, 'Y-m-d') }}</span>@endif
                @if ($consent->phone_normalized !== $customer->normalized_contact_phone)<strong> (хуучин дугаар — хүчингүй)</strong>@endif
                <span style="color:#58656C;font-size:12px;">· {{ $consent->source }}</span>
            </div>
        @empty
            <p style="color:#58656C;">Зөвшөөрөл бүртгэгдээгүй — сануулга илгээгдэхгүй.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;">Дараагийн үйлчилгээ</h3>
        @forelse ($plans as $plan)
            <div style="border-top:1px solid #eee;padding:6px 0;font-size:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <x-filament::badge :color="$plan->status->getColor()">{{ $plan->status->getLabel() }}</x-filament::badge>
                {{ $plan->vehicle->label() }} — {{ $plan->description }}
                @if ($plan->due_date)· {{ $plan->due_date->format('Y-m-d') }}@endif
                @if ($plan->due_km)· {{ number_format($plan->due_km, 0, '.', ' ') }} км@endif
            </div>
        @empty
            <p style="color:#58656C;">Төлөвлөгөө алга.</p>
        @endforelse
    </div>
</div>
