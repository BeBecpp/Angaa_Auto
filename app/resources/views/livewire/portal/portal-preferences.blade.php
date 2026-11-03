<div class="space-y-6">
    <section class="card">
        <h2 class="text-lg">Баталгаажсан утас</h2>
        <p class="mt-1 money text-xl">{{ $verifiedPhone }}</p>
        <p class="mt-1 text-sm text-muted">Утас солих бол сервис рүү холбогдоно уу — шинэ дугаарыг дахин баталгаажуулна.</p>
    </section>

    @if ($customer)
        <form wire:submit="save" class="card space-y-4">
            <h2 class="text-lg">Мессежийн тохиргоо</h2>
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="serviceReminders" class="mt-1 size-5 accent-[#0B5A60]">
                <span><span class="font-semibold">Үйлчилгээний сануулга (SMS)</span><span class="block text-sm text-muted">Цагийн сануулга, дараагийн үйлчилгээний огноо/км. 20:00–09:00 хооронд илгээхгүй.</span></span>
            </label>
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="marketing" class="mt-1 size-5 accent-[#0B5A60]">
                <span><span class="font-semibold">Мэдээ, урамшуулал</span><span class="block text-sm text-muted">Анхнаасаа унтраалттай. Хүссэн үедээ зогсооно.</span></span>
            </label>
            @if ($saved)<p class="rounded-[8px] bg-success-soft p-2 text-sm font-medium text-success" role="status">✓ {{ $saved }}</p>@endif
            <button type="submit" class="btn-primary">Хадгалах</button>
        </form>

        <section class="card">
            <h2 class="text-lg">Миний мэдээлэл</h2>
            <p class="mt-1 text-sm text-muted">Өөрийн мэдээллийн хуулбар авах хүсэлт илгээвэл ажилтан бэлтгэж танд өгнө.</p>
            @if ($exportRequested)
                <p class="mt-3 rounded-[8px] bg-success-soft p-2 text-sm font-medium text-success" role="status">✓ Хүсэлтийг бүртгэлээ.</p>
            @else
                <button type="button" wire:click="requestExport" class="btn-secondary mt-3">Мэдээллийн хуулбар хүсэх</button>
            @endif
        </section>
    @else
        <x-empty-state title="Бүртгэл холбогдоогүй байна">Ажилтан таны бүртгэлийг холбосны дараа тохиргоо идэвхжинэ.</x-empty-state>
    @endif
</div>
