<div class="space-y-6">
    <section aria-labelledby="arrivals-h">
        <h1 id="arrivals-h" class="text-2xl">Өнөөдөр ирэх машин</h1>
        @forelse ($arrivals as $appointment)
            <div class="card mt-3" wire:key="arrival-{{ $appointment->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="money text-xl font-bold">{{ \App\Support\BusinessTime::format($appointment->planned_start, 'H:i') }}</p>
                        <p class="font-semibold"><x-plate-badge :plate="$appointment->vehicle?->current_plate_display" /> {{ $appointment->vehicle?->make }} {{ $appointment->vehicle?->model }}</p>
                        <p class="text-sm text-muted">{{ $appointment->service?->name_mn ?? 'Үйлчилгээ' }} · {{ $appointment->customer->firstName() }}</p>
                    </div>
                    @if ($checkingInId !== $appointment->id)
                        <button type="button" wire:click="startCheckIn({{ $appointment->id }})" class="btn-primary min-h-12">Хүлээн авах</button>
                    @endif
                </div>

                @if ($checkingInId === $appointment->id)
                    <form wire:submit="checkIn" class="mt-4 space-y-4 border-t border-line pt-4">
                        @error('checkin') <p class="rounded-[8px] bg-danger-soft p-3 font-medium text-danger" role="alert">{{ $message }}</p> @enderror
                        <div>
                            <label for="complaint" class="field-label">Гомдол / хүсэлт</label>
                            <textarea id="complaint" wire:model="complaint" rows="3" class="field-input"></textarea>
                            @error('complaint') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="intakeKm" class="field-label">Гүйлт (км)</label>
                                <input id="intakeKm" type="text" inputmode="numeric" wire:model="intakeKm" class="field-input money">
                                @error('intakeKm') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="fuelLevel" class="field-label">Түлш</label>
                                <select id="fuelLevel" wire:model="fuelLevel" class="field-input">
                                    <option value="">—</option>
                                    @foreach (['хоосон', '1/4', '1/2', '3/4', 'дүүрэн'] as $level)<option value="{{ $level }}">{{ $level }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="damage" class="field-label">Харагдах гэмтэл</label>
                            <input id="damage" type="text" wire:model="damage" class="field-input">
                        </div>
                        <div>
                            <label for="belongings" class="field-label">Машинд үлдсэн эд зүйл</label>
                            <input id="belongings" type="text" wire:model="belongings" class="field-input">
                        </div>
                        <div>
                            <label for="kmCorrection" class="field-label">Км буурсан бол залруулгын шалтгаан</label>
                            <input id="kmCorrection" type="text" wire:model="kmCorrection" class="field-input">
                            <p class="field-hint">Км залруулгыг зөвхөн зөвлөх/эзэн баталгаажуулна.</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="btn-primary min-h-12 flex-1" wire:loading.attr="disabled">Хүлээн авах</button>
                            <button type="button" wire:click="$set('checkingInId', null)" class="btn-secondary min-h-12">Болих</button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <p class="mt-2 text-muted">Өнөөдөр танд товлосон машин алга.</p>
        @endforelse
    </section>

    <section aria-labelledby="orders-h">
        <h2 id="orders-h" class="text-2xl">Нээлттэй ажил</h2>
        @forelse ($orders as $order)
            <a href="{{ route('mechanic.job', $order) }}" class="card mt-3 flex min-h-12 items-center justify-between gap-3 hover:border-primary" wire:key="order-{{ $order->id }}">
                <span>
                    <span class="block font-semibold"><x-plate-badge :plate="$order->vehicle_snapshot['plate'] ?? null" /> {{ $order->vehicle_snapshot['make'] ?? '' }} {{ $order->vehicle_snapshot['model'] ?? '' }}</span>
                    <span class="block text-sm text-muted">{{ $order->order_number }}</span>
                </span>
                <x-status-badge :status="$order->status" />
            </a>
        @empty
            <x-empty-state class="mt-3" title="Нээлттэй ажил алга" />
        @endforelse
    </section>
</div>
