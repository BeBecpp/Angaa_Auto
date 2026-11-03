<x-filament-widgets::widget>
    <x-filament::section heading="Анхааруулга ба мөнгө">
        <ul style="display:grid;gap:6px;font-size:14px;">
            @foreach ($alerts as $alert)
                <li style="display:flex;justify-content:space-between;gap:8px;">
                    <span>{{ $alert['label'] }}</span>
                    <x-filament::badge :color="$alert['count'] > 0 ? $alert['tone'] : 'gray'">{{ $alert['count'] }}</x-filament::badge>
                </li>
            @endforeach
        </ul>

        <table style="width:100%;margin-top:16px;font-size:14px;font-variant-numeric:tabular-nums;border-collapse:collapse;">
            <thead><tr style="color:#58656C;text-align:right;"><th></th><th style="padding:4px">Өнөөдөр</th><th style="padding:4px">Энэ сар</th></tr></thead>
            <tbody>
                <tr style="border-top:1px solid #eee;"><td>Нэхэмжилсэн</td><td style="text-align:right;padding:4px">{{ \App\Support\Money::format($billedToday) }}</td><td style="text-align:right;padding:4px">{{ \App\Support\Money::format($billedMonth) }}</td></tr>
                <tr style="border-top:1px solid #eee;"><td>Хүлээн авсан мөнгө</td><td style="text-align:right;padding:4px">{{ \App\Support\Money::format($receivedToday) }}</td><td style="text-align:right;padding:4px">{{ \App\Support\Money::format($receivedMonth) }}</td></tr>
                <tr style="border-top:2px solid #142632;font-weight:700;"><td>Нийт авлага</td><td colspan="2" style="text-align:right;padding:4px">{{ \App\Support\Money::format($outstanding) }}</td></tr>
            </tbody>
        </table>
        <p style="font-size:12px;color:#58656C;margin-top:6px;">Нэхэмжилсэн дүн ба бодит орсон мөнгө тусдаа. Өртөг бүрэн бүртгэгдээгүй тул «цэвэр ашиг» тооцохгүй.</p>

        <p style="font-size:12px;margin-top:10px;">
            Scheduler: {{ $scheduler ? \App\Support\BusinessTime::format($scheduler, 'm/d H:i') : 'ажиллаагүй' }} ·
            Queue worker: {{ $worker ? \App\Support\BusinessTime::format($worker, 'm/d H:i') : 'ажиллаагүй' }}
            @if (! $scheduler || $scheduler->lt(now()->subMinutes(5)))<strong style="color:#B42318;"> — сануулга автоматаар ажиллахгүй байна</strong>@endif
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
