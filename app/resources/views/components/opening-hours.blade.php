@props(['hours'])
@php($isDemo = $hours->flatten()->contains('is_demo', true))
<div class="card">
    <h3 class="text-lg">Ажлын цаг</h3>
    @if ($hours->isEmpty())
        <p class="mt-2 text-muted">Ажлын цагийг удахгүй нийтэлнэ. Утсаар лавлана уу.</p>
    @else
        @if ($isDemo)
            <p class="mt-1 text-sm font-semibold text-danger">DEMO цаг — бодит цагийн хуваарь биш.</p>
        @endif
        <dl class="mt-3 divide-y divide-line">
            @foreach (range(1, 7) as $day)
                <div class="flex justify-between py-2 text-sm">
                    <dt>{{ \App\Support\BusinessTime::weekdayLabel($day) }}</dt>
                    <dd class="money">
                        @if ($hours->has($day))
                            {{ $hours[$day]->map(fn ($rule) => $rule->local_start.'–'.$rule->local_end)->join(', ') }}
                        @else
                            <span class="text-muted">Амарна</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
