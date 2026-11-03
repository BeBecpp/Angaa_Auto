<x-filament-widgets::widget>
    <x-filament::section heading="Өнөөдрийн жагсаалт">
        @if ($appointments->isEmpty())
            <p style="color:#58656C;">Өнөөдөр цаг алга.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:14px;border-collapse:collapse;">
                    <thead><tr style="text-align:left;color:#58656C;"><th style="padding:4px">Цаг</th><th style="padding:4px">Машин</th><th style="padding:4px">Үйлчлүүлэгч</th><th style="padding:4px">Пост / механик</th><th style="padding:4px">Дараагийн алхам</th></tr></thead>
                    <tbody>
                        @foreach ($appointments as $appointment)
                            <tr style="border-top:1px solid #eee;">
                                <td style="padding:4px;font-variant-numeric:tabular-nums;font-weight:600;">{{ \App\Support\BusinessTime::format($appointment->planned_start, 'H:i') }}</td>
                                <td style="padding:4px;">{{ $appointment->vehicle?->current_plate_display }} {{ $appointment->vehicle?->model ?? $appointment->vehicle?->make }}<br><span style="color:#58656C;font-size:12px;">{{ $appointment->service?->name_mn }}</span></td>
                                <td style="padding:4px;">{{ $appointment->customer->firstName() }}</td>
                                <td style="padding:4px;">{{ $appointment->bay?->name ?? '—' }} / {{ $appointment->mechanic?->name }}</td>
                                <td style="padding:4px;">
                                    <a style="color:#0B5A60;text-decoration:underline;" href="{{ $appointment->workOrder ? \App\Filament\Resources\WorkOrders\WorkOrderResource::getUrl('view', ['record' => $appointment->workOrder]) : \App\Filament\Resources\Appointments\AppointmentResource::getUrl('edit', ['record' => $appointment]) }}">{{ $nextAction($appointment) }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
