<x-filament-panels::page>
    @vite(['resources/js/calendar.js'])

    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px;">
        <label style="min-width:200px;">
            <span style="display:block;font-size:14px;font-weight:500;margin-bottom:4px;">Пост</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="bayFilter">
                    <option value="">Бүгд</option>
                    @foreach ($bays as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label style="min-width:220px;">
            <span style="display:block;font-size:14px;font-weight:500;margin-bottom:4px;">Механик</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="mechanicFilter">
                    <option value="">Бүгд</option>
                    @foreach ($mechanics as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <p style="font-size:13px;color:#58656C;max-width:640px;">Бүх цаг Улаанбаатарын цагаар (төхөөрөмжийн цагийн бүсээс үл хамаарна). Чирж зөөхөд баталгаажуулалт асууна; давхцвал буцна.
            Гараар засах: <a style="text-decoration:underline;" href="{{ \App\Filament\Resources\Appointments\AppointmentResource::getUrl() }}">цагийн жагсаалт</a>.</p>
    </div>

    <div wire:ignore x-data="angaaCalendar" style="background:#fff;border-radius:12px;padding:12px;border:1px solid #D9DFDE;">
        <div x-ref="calendar"></div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:13px;" aria-label="Тайлбар">
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#0B5A60"></span> Баталгаажсан</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#142632"></span> Ирсэн</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#187047"></span> Дууссан</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#58656C"></span> Цуцалсан / ирээгүй</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#B42318"></span> Хугацаа хэтэрсэн (пост эзэлсэн)</span>
    </div>
</x-filament-panels::page>
