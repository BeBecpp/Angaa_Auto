@php($events = $order->statusEvents()->with('actor')->orderBy('occurred_at')->get())
<ol style="display:grid;gap:6px;font-size:14px;">
    @foreach ($events as $event)
        <li style="border-left:3px solid #0B5A60;padding-left:8px;">
            <strong>{{ $event->to_status->getLabel() }}</strong>
            @if ($event->from_status)<span style="color:#58656C;">← {{ $event->from_status->getLabel() }}</span>@endif
            <br><span style="font-size:12px;color:#58656C;">{{ \App\Support\BusinessTime::format($event->occurred_at) }} · {{ $event->actor?->name ?? 'систем' }}</span>
            @if ($event->reason)<br><span>{{ $event->reason }}</span>@endif
        </li>
    @endforeach
</ol>
