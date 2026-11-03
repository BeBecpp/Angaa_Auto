<div class="rounded-[16px] border-2 border-accent bg-surface p-5 shadow-card">
    <h2 class="text-xl">Таны зөвшөөрөл хүлээж буй үнийн санал</h2>
    <p class="mt-1 text-sm text-muted">Доорх хувилбар, дүнг яг энэ хэвээр нь зөвшөөрнө. Нэмэлт ажил гарвал шинэ хувилбараар дахин асууна.</p>

    <x-estimate-revision :revision="$revision" :showDecision="false" class="mt-4" />

    @if ($result)
        <p class="mt-4 rounded-[8px] bg-success-soft p-3 font-medium text-success" role="status">✓ {{ $result }}</p>
    @endif

    @error('decision')
        <p class="mt-4 rounded-[8px] bg-danger-soft p-3 font-medium text-danger" role="alert">{{ $message }}</p>
    @enderror

    @if ($actionable)
        @if ($confirming === null)
            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                <button type="button" wire:click="ask('approved')" class="btn-primary flex-1">Зөвшөөрөх — <x-money :amount="$revision->grand_total_mnt" /></button>
                <button type="button" wire:click="ask('declined')" class="btn-secondary flex-1">Татгалзах</button>
            </div>
            @if ($phone = \App\Support\Settings::contactPhone())
                <a href="tel:{{ $phone }}" class="mt-3 inline-block text-sm text-primary underline">Асуух зүйл байна — залгах</a>
            @endif
        @else
            <div class="mt-5 rounded-[12px] border border-line bg-canvas p-4" role="dialog" aria-modal="false" aria-labelledby="confirm-h">
                @if ($confirming === 'approved')
                    <p id="confirm-h" class="font-semibold">Хувилбар #{{ $revision->revision_number }}, нийт <x-money :amount="$revision->grand_total_mnt" />-г зөвшөөрөх үү?</p>
                @else
                    <p id="confirm-h" class="font-semibold">Хувилбар #{{ $revision->revision_number }}-ээс татгалзах уу?</p>
                    <label for="declineReason" class="field-label mt-3">Шалтгаан (заавал биш)</label>
                    <textarea id="declineReason" wire:model="declineReason" rows="2" class="field-input"></textarea>
                @endif
                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <button type="button" wire:click="decide" wire:loading.attr="disabled" class="{{ $confirming === 'approved' ? 'btn-primary' : 'btn-danger' }} flex-1">
                        <span wire:loading.remove wire:target="decide">Тийм, баталгаажуулах</span>
                        <span wire:loading wire:target="decide">Бүртгэж байна…</span>
                    </button>
                    <button type="button" wire:click="$set('confirming', null)" class="btn-secondary flex-1">Болих</button>
                </div>
            </div>
        @endif
    @endif
</div>
