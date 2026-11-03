<x-layouts.portal title="Миний цаг">
    <h1 class="text-[28px]">Миний цаг</h1>
    <p class="mt-1 text-muted">Цагийг өөрчлөх, цуцлах хүсэлтийг ажилтан хянаж баталгаажуулна. Хүсэлт илгээх нь шууд өөрчлөгдсөн гэсэн үг биш.</p>
    @forelse ($appointments as $appointment)
        <div class="card mt-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-lg font-semibold money">{{ \App\Support\BusinessTime::format($appointment->planned_start, 'Y-m-d H:i') }} <span class="text-sm font-normal text-muted">(Улаанбаатарын цаг)</span></p>
                <x-status-badge :status="$appointment->status" />
            </div>
            <p class="text-muted">{{ $appointment->service?->name_mn ?? 'Үйлчилгээ' }} · {{ $appointment->vehicle?->label() }}</p>
            @if ($appointment->status === \App\Enums\AppointmentStatus::Confirmed && $appointment->planned_start->isFuture())
                <livewire:portal.appointment-change :appointment="$appointment" :key="'change-'.$appointment->id" />
            @endif
        </div>
    @empty
        <x-empty-state class="mt-6" title="Цаг алга">
            <x-slot:action><a href="{{ route('request.create') }}" class="btn-primary">Засварын хүсэлт өгөх</a></x-slot:action>
        </x-empty-state>
    @endforelse
</x-layouts.portal>
