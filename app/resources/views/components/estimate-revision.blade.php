@props(['revision', 'showDecision' => true])
<article {{ $attributes->merge(['class' => 'rounded-[12px] border border-line p-4']) }}>
    <header class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-base font-semibold">Хувилбар #{{ $revision->revision_number }}
            <span class="font-normal text-muted">· {{ \App\Support\BusinessTime::format($revision->published_at, 'Y-m-d') }}</span></h3>
        <x-status-badge :status="$revision->state" />
    </header>
    @if ($revision->diagnosis_summary)
        <p class="mt-2 whitespace-pre-line text-sm">{{ $revision->diagnosis_summary }}</p>
    @endif
    {{-- Phones: stacked lines; wider screens: table. --}}
    <ul class="mt-3 divide-y divide-line sm:hidden">
        @foreach ($revision->lines->sortBy('sort_order') as $line)
            <li class="py-2">
                <div class="flex justify-between gap-3">
                    <span><span class="block text-xs text-muted">{{ $line->kind->getLabel() }}</span>{{ $line->description }}</span>
                    <x-money :amount="$line->total_mnt" class="font-semibold" />
                </div>
                <p class="text-sm text-muted money">{{ \App\Support\Money::milliToDisplay($line->quantity_milli) }} × {{ \App\Support\Money::format($line->unit_price_mnt) }}@if ($line->discount_mnt) − {{ \App\Support\Money::format($line->discount_mnt) }}@endif</p>
            </li>
        @endforeach
        <li class="flex justify-between py-1 text-sm"><span>Ажлын хөлс</span><x-money :amount="$revision->labor_total_mnt" /></li>
        <li class="flex justify-between py-1 text-sm"><span>Сэлбэг</span><x-money :amount="$revision->parts_total_mnt" /></li>
        <li class="flex justify-between border-t-2 border-ink py-2 text-base font-bold"><span>Нийт</span><x-money :amount="$revision->grand_total_mnt" /></li>
    </ul>
    <div class="mt-3 hidden overflow-x-auto sm:block">
        <table class="w-full text-sm">
            <thead class="text-left text-muted">
                <tr><th class="py-1 font-medium">Төрөл</th><th class="py-1 font-medium">Тайлбар</th><th class="py-1 text-right font-medium">Тоо</th><th class="py-1 text-right font-medium">Нэгж үнэ</th><th class="py-1 text-right font-medium">Хөнгөлөлт</th><th class="py-1 text-right font-medium">Дүн</th></tr>
            </thead>
            <tbody>
                @foreach ($revision->lines->sortBy('sort_order') as $line)
                    <tr class="border-t border-line">
                        <td class="py-1.5">{{ $line->kind->getLabel() }}</td>
                        <td class="py-1.5">{{ $line->description }}</td>
                        <td class="py-1.5 text-right money">{{ \App\Support\Money::milliToDisplay($line->quantity_milli) }}</td>
                        <td class="py-1.5 text-right"><x-money :amount="$line->unit_price_mnt" /></td>
                        <td class="py-1.5 text-right">@if ($line->discount_mnt)<x-money :amount="$line->discount_mnt" />@else — @endif</td>
                        <td class="py-1.5 text-right font-semibold"><x-money :amount="$line->total_mnt" /></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-ink"><td colspan="5" class="py-1.5 text-right">Ажлын хөлс</td><td class="py-1.5 text-right"><x-money :amount="$revision->labor_total_mnt" /></td></tr>
                <tr><td colspan="5" class="py-1 text-right">Сэлбэг</td><td class="py-1 text-right"><x-money :amount="$revision->parts_total_mnt" /></td></tr>
                <tr class="text-base font-bold"><td colspan="5" class="py-1.5 text-right">Нийт</td><td class="py-1.5 text-right"><x-money :amount="$revision->grand_total_mnt" /></td></tr>
            </tfoot>
        </table>
    </div>
    @if ($revision->exclusions)<p class="mt-2 text-sm"><span class="font-semibold">Багтаагүй:</span> {{ $revision->exclusions }}</p>@endif
    @if ($revision->terms)<p class="mt-1 text-sm"><span class="font-semibold">Нөхцөл:</span> {{ $revision->terms }}</p>@endif
    @if ($revision->valid_until)<p class="mt-1 text-sm text-muted">Хүчинтэй: {{ $revision->valid_until->format('Y-m-d') }} хүртэл</p>@endif
    @if ($showDecision && $revision->decision)
        <p class="mt-3 rounded-[8px] bg-canvas p-2 text-sm">
            {{ $revision->decision->decision->getLabel() }} — {{ $revision->decision->channel->getLabel() }}, {{ \App\Support\BusinessTime::format($revision->decision->decided_at) }},
            дүн <x-money :amount="$revision->decision->amount_snapshot_mnt" />
        </p>
    @endif
</article>
