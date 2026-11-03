@php($revisions = $order->estimateRevisions()->with(['lines', 'decision.actor'])->orderByDesc('revision_number')->get())
<div style="display:flex;flex-direction:column;gap:16px;">
    @if ($order->currentApprovedRevision)
        <p style="font-size:14px;">Одоогийн зөвшөөрсөн хүрээ: <strong>хувилбар #{{ $order->currentApprovedRevision->revision_number }}</strong> — {{ \App\Support\Money::format($order->currentApprovedRevision->grand_total_mnt) }}</p>
    @endif
    @forelse ($revisions as $revision)
        <div style="border:1px solid #D9DFDE;border-radius:12px;padding:12px;">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <strong>Хувилбар #{{ $revision->revision_number }}</strong>
                <x-filament::badge :color="$revision->state->getColor()">{{ $revision->state->getLabel() }}</x-filament::badge>
            </div>
            @if ($revision->diagnosis_summary)<p style="font-size:14px;margin-top:6px;white-space:pre-line;">{{ $revision->diagnosis_summary }}</p>@endif
            <table style="width:100%;font-size:13px;margin-top:8px;border-collapse:collapse;">
                <thead><tr style="text-align:left;color:#58656C;"><th>Төрөл</th><th>Тайлбар</th><th style="text-align:right">Тоо</th><th style="text-align:right">Нэгж</th><th style="text-align:right">Хөнг.</th><th style="text-align:right">Дүн</th></tr></thead>
                <tbody>
                    @foreach ($revision->lines->sortBy('sort_order') as $line)
                        <tr style="border-top:1px solid #eee;">
                            <td>{{ $line->kind->getLabel() }}</td><td>{{ $line->description }}</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums">{{ \App\Support\Money::milliToDisplay($line->quantity_milli) }}</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($line->unit_price_mnt) }}</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $line->discount_mnt ? \App\Support\Money::format($line->discount_mnt) : '—' }}</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($line->total_mnt) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr style="border-top:2px solid #142632;font-weight:700;"><td colspan="5" style="text-align:right">Нийт (ажил {{ \App\Support\Money::format($revision->labor_total_mnt) }} · сэлбэг {{ \App\Support\Money::format($revision->parts_total_mnt) }})</td><td style="text-align:right;font-variant-numeric:tabular-nums">{{ \App\Support\Money::format($revision->grand_total_mnt) }}</td></tr></tfoot>
            </table>
            <p style="font-size:12px;color:#58656C;margin-top:6px;">
                @if ($revision->published_at)Илгээсэн {{ \App\Support\BusinessTime::format($revision->published_at) }} · @endif
                @if ($revision->valid_until)Хүчинтэй {{ $revision->valid_until->format('Y-m-d') }} хүртэл · @endif
                @if ($revision->content_hash)hash {{ substr($revision->content_hash, 0, 10) }}@endif
            </p>
            @if ($revision->decision)
                <p style="font-size:13px;margin-top:6px;background:#F5F3EE;padding:6px 8px;border-radius:8px;">
                    <strong>{{ $revision->decision->decision->getLabel() }}</strong> — {{ $revision->decision->channel->getLabel() }},
                    {{ \App\Support\BusinessTime::format($revision->decision->decided_at) }}, {{ \App\Support\Money::format($revision->decision->amount_snapshot_mnt) }}
                    @if ($revision->decision->staff_attestation_note)<br>Ажилтан ({{ $revision->decision->actor?->name }}): {{ $revision->decision->staff_attestation_note }}@endif
                    @if ($revision->decision->reason)<br>Шалтгаан: {{ $revision->decision->reason }}@endif
                </p>
            @endif
        </div>
    @empty
        <p style="color:#58656C;">Үнийн санал алга. «Шинэ үнийн санал» товчоор ноорог үүсгэнэ.</p>
    @endforelse
    @if ($order->declined_recommendations)
        <p style="font-size:13px;"><strong>Татгалзсан зөвлөмж:</strong> <span style="white-space:pre-line">{{ $order->declined_recommendations }}</span></p>
    @endif
</div>
