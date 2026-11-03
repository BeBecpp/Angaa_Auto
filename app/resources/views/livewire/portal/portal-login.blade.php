<div class="card">
    @if (! $available)
        <h2 class="text-xl">Утсаар нэвтрэх түр боломжгүй</h2>
        <p class="mt-2 text-muted">SMS үйлчилгээ хараахан идэвхжээгүй тул порталд нэвтрэх боломжгүй байна. Машиныхаа мэдээллийг сервис рүү холбогдож авна уу.</p>
    @elseif (! $codeSent)
        <form wire:submit="sendCode" class="space-y-4" novalidate>
            <div>
                <label for="phone" class="field-label">Утасны дугаар</label>
                <input id="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="9911 2233" class="field-input" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                @error('phone') <p id="phone-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sendCode">Код авах</span>
                <span wire:loading wire:target="sendCode">Илгээж байна…</span>
            </button>
            <p class="text-sm text-muted">Сервис дээр бүртгүүлсэн утасны дугаараа оруулна. Улсын дугаараар бусдын түүхийг хайх боломжгүй.</p>
        </form>
    @else
        <form wire:submit="verify" class="space-y-4" novalidate>
            @if ($notice)<p class="rounded-[8px] bg-primary-soft p-3 text-sm text-primary" role="status">{{ $notice }}</p>@endif
            <div>
                <label for="code" class="field-label">6 оронтой код</label>
                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code" class="field-input money text-2xl tracking-[0.4em]" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code') <p id="code-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="verify">Нэвтрэх</span>
                <span wire:loading wire:target="verify">Шалгаж байна…</span>
            </button>
            <div class="flex flex-wrap justify-between gap-2 text-sm">
                <button type="button" wire:click="$set('codeSent', false)" class="min-h-11 text-primary underline">Дугаар солих</button>
                <button type="button" wire:click="sendCode" class="min-h-11 text-primary underline">Код дахин авах</button>
            </div>

            @if ($localCode)
                <div class="rounded-[8px] border-2 border-dashed border-accent bg-accent/15 p-3 text-sm" role="note">
                    <p class="font-semibold">Зөвхөн туршилтын орчин (local): SMS илгээгдээгүй.</p>
                    <p>Энэ хөтчийн нэвтрэх оролдлогын код: <span class="money text-lg font-bold">{{ $localCode }}</span></p>
                </div>
            @endif
        </form>
    @endif
</div>
