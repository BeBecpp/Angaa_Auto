<x-filament-panels::page>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
        <label style="font-size:14px;">Эхлэх (УБ)<br><input type="date" wire:model.live="from" style="min-height:40px;border:1px solid #D9DFDE;border-radius:8px;padding:0 8px;"></label>
        <label style="font-size:14px;">Дуусах (УБ)<br><input type="date" wire:model.live="to" style="min-height:40px;border:1px solid #D9DFDE;border-radius:8px;padding:0 8px;"></label>
    </div>

    @if (! $report)
        <p style="color:#B42318;">Огнооны муж буруу байна.</p>
    @else
        @php($money = fn (int $v): string => \App\Support\Money::format($v))
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
            <x-filament::section heading="Хүсэлтээс ажил хүртэл">
                <dl style="display:grid;grid-template-columns:1fr auto;gap:4px;font-size:14px;font-variant-numeric:tabular-nums;">
                    <dt>Хүсэлт (спамгүй)</dt><dd>{{ $report['requests'] }}</dd>
                    <dt>Цаг болсон</dt><dd>{{ $report['converted'] }}</dd>
                    <dt>Ирсэн</dt><dd>{{ $report['arrived'] }}</dd>
                    <dt>Олгосон ажил</dt><dd>{{ $report['completed'] }}</dd>
                </dl>
            </x-filament::section>
            <x-filament::section heading="Хариу ба ирц">
                <dl style="display:grid;grid-template-columns:1fr auto;gap:4px;font-size:14px;font-variant-numeric:tabular-nums;">
                    <dt>Анх холбогдох — медиан</dt><dd>{{ $report['response_median_h'] !== null ? $report['response_median_h'].' цаг' : '—' }}</dd>
                    <dt>Анх холбогдох — P90</dt><dd>{{ $report['response_p90_h'] !== null ? $report['response_p90_h'].' цаг' : '—' }}</dd>
                    <dt>Ирээгүй хувь</dt><dd>{{ $report['no_show_rate'] !== null ? $report['no_show_rate'].'%' : '—' }}</dd>
                    <dt>Давтан үйлчлүүлэгч</dt><dd>{{ $report['repeat_customers'] }}</dd>
                </dl>
                <p style="font-size:12px;color:#58656C;">Хариу өгөх хугацааг ажлын бус цагийг хасалгүй тооцсон.</p>
            </x-filament::section>
            <x-filament::section heading="Үнийн санал">
                <dl style="display:grid;grid-template-columns:1fr auto;gap:4px;font-size:14px;font-variant-numeric:tabular-nums;">
                    <dt>Зөвшөөрсөн</dt><dd>{{ $report['approved'] }}</dd>
                    <dt>Татгалзсан</dt><dd>{{ $report['declined'] }}</dd>
                    <dt>Зөвшөөрлийн хувь</dt><dd>{{ $report['approval_rate'] !== null ? $report['approval_rate'].'%' : '—' }}</dd>
                    <dt>Сануулгын дараа дахин цаг авсан</dt><dd>{{ $report['rebooked_from_reminder'] }}</dd>
                </dl>
            </x-filament::section>
            <x-filament::section heading="Мөнгө">
                <dl style="display:grid;grid-template-columns:1fr auto;gap:4px;font-size:14px;font-variant-numeric:tabular-nums;">
                    <dt>Нэхэмжилсэн</dt><dd>{{ $money($report['billed']) }}</dd>
                    <dt>Хүлээн авсан</dt><dd>{{ $money($report['received']) }}</dd>
                    <dt>Одоогийн нийт авлага</dt><dd>{{ $money($report['outstanding']) }}</dd>
                </dl>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
