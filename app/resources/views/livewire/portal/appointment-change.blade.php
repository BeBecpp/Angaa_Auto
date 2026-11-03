<div class="mt-4">
    @if ($appointment->customer_change_request)
        <p class="rounded-[8px] bg-accent/20 p-3 text-sm" role="status">
            <span class="font-semibold">Хүлээгдэж буй хүсэлт:</span> {{ $appointment->customer_change_request }}
            <span class="block text-muted">Илгээсэн {{ \App\Support\BusinessTime::format($appointment->customer_change_requested_at) }}. Ажилтан баталгаажуулах хүртэл одоогийн цаг хүчинтэй.</span>
        </p>
    @elseif (! $open)
        <button type="button" wire:click="$set('open', true)" class="btn-secondary">Өөрчлөх / цуцлах хүсэлт</button>
    @else
        <form wire:submit="submit" class="space-y-3 rounded-[12px] border border-line bg-canvas p-4">
            <fieldset>
                <legend class="field-label">Юу хүсэж байна вэ?</legend>
                <label class="mr-4 inline-flex min-h-11 items-center gap-2"><input type="radio" wire:model="kind" value="reschedule" class="size-5 accent-[#0B5A60]"> Цаг өөрчлөх</label>
                <label class="inline-flex min-h-11 items-center gap-2"><input type="radio" wire:model="kind" value="cancel" class="size-5 accent-[#0B5A60]"> Цуцлах</label>
            </fieldset>
            <div>
                <label for="reason-{{ $appointment->id }}" class="field-label">Шалтгаан / хүссэн цаг</label>
                <textarea id="reason-{{ $appointment->id }}" wire:model="reason" rows="2" class="field-input"></textarea>
                @error('reason') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary">Хүсэлт илгээх</button>
                <button type="button" wire:click="$set('open', false)" class="btn-secondary">Болих</button>
            </div>
        </form>
    @endif
</div>
