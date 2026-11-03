@php($status = $order->status)
<div class="space-y-5">
    <a href="{{ route('mechanic.index') }}" class="text-sm text-primary underline">← Миний ажил</a>
    <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl"><x-plate-badge :plate="$order->vehicle_snapshot['plate'] ?? null" /> {{ $order->vehicle_snapshot['make'] ?? '' }} {{ $order->vehicle_snapshot['model'] ?? '' }}</h1>
            <x-status-badge :status="$status" />
        </div>
        <p class="mt-1 text-sm text-muted">{{ $order->order_number }} · {{ $order->customer_snapshot['display_name'] ?? '' }} @if ($order->intake_km)· <span class="money">{{ number_format($order->intake_km, 0, '.', ' ') }} км</span>@endif</p>
        <p class="mt-3 whitespace-pre-line">{{ $order->complaint }}</p>
        @if ($order->visible_damage_note)<p class="mt-2 text-sm"><span class="font-semibold">Гэмтэл:</span> {{ $order->visible_damage_note }}</p>@endif
    </div>

    @if ($flash)<p class="rounded-[8px] bg-success-soft p-3 font-medium text-success" role="status">✓ {{ $flash }}</p>@endif
    @error('action')<p class="rounded-[8px] bg-danger-soft p-3 font-medium text-danger" role="alert">{{ $message }}</p>@enderror

    {{-- One primary action per state --}}
    <section class="card" aria-labelledby="next-h">
        <h2 id="next-h" class="text-lg">Дараагийн алхам</h2>
        @switch($status)
            @case(\App\Enums\WorkOrderStatus::Received)
                <button type="button" wire:click="moveStatus('diagnosing')" class="btn-primary mt-3 min-h-12 w-full">Онош эхлүүлэх</button>
                @break
            @case(\App\Enums\WorkOrderStatus::Diagnosing)
                <p class="mt-2 text-muted">Үзлэгийн мөр, зураг нэмнэ үү. Зөвлөх үнийн санал гаргаж хэрэглэгчээс зөвшөөрөл авна.</p>
                @break
            @case(\App\Enums\WorkOrderStatus::AwaitingApproval)
                <p class="mt-2 font-semibold">Хэрэглэгчийн зөвшөөрөл хүлээж байна — засвар эхлүүлэхгүй.</p>
                @break
            @case(\App\Enums\WorkOrderStatus::Approved)
                <button type="button" wire:click="moveStatus('in_progress')" class="btn-primary mt-3 min-h-12 w-full">Засвар эхлүүлэх</button>
                @break
            @case(\App\Enums\WorkOrderStatus::InProgress)
                <button type="button" wire:click="moveStatus('quality_check')" wire:confirm="Засвар дууссан бөгөөд чанарын шалгалтад шилжүүлэх үү?" class="btn-primary mt-3 min-h-12 w-full">Чанарын шалгалтад шилжүүлэх</button>
                <details class="mt-3">
                    <summary class="min-h-11 cursor-pointer text-primary underline">Сэлбэг хүлээх төлөвт оруулах</summary>
                    <label for="waitReason" class="field-label mt-2">Ямар сэлбэг, хэзээ ирэх</label>
                    <input id="waitReason" type="text" wire:model="waitReason" class="field-input">
                    <button type="button" wire:click="moveStatus('waiting_parts')" class="btn-secondary mt-2 min-h-12 w-full">Сэлбэг хүлээх</button>
                </details>
                @break
            @case(\App\Enums\WorkOrderStatus::WaitingParts)
                <button type="button" wire:click="moveStatus('{{ ($order->status_before_wait ?? \App\Enums\WorkOrderStatus::InProgress)->value }}')" class="btn-primary mt-3 min-h-12 w-full">Сэлбэг ирсэн — үргэлжлүүлэх</button>
                @break
            @default
                <p class="mt-2 text-muted">{{ $status->getLabel() }} — механикаас хийх үйлдэл алга.</p>
        @endswitch
    </section>

    {{-- Approved scope & repair log (no prices) --}}
    @if ($order->currentApprovedRevision)
        <section class="card" aria-labelledby="scope-h">
            <h2 id="scope-h" class="text-lg">Зөвшөөрсөн ажил (хувилбар #{{ $order->currentApprovedRevision->revision_number }})</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($order->currentApprovedRevision->lines->sortBy('sort_order') as $line)
                    <li class="flex items-start gap-2">
                        <span aria-hidden="true" class="{{ in_array($line->id, $doneLineIds) ? 'text-success' : 'text-muted' }}">{{ in_array($line->id, $doneLineIds) ? '✓' : '○' }}</span>
                        <span>{{ $line->kind->getLabel() }}: {{ $line->description }} × {{ \App\Support\Money::milliToDisplay($line->quantity_milli) }}
                            <span class="sr-only">{{ in_array($line->id, $doneLineIds) ? 'хийсэн' : 'хийгээгүй' }}</span></span>
                    </li>
                @endforeach
            </ul>
            @if ($status->allowsRepairWork())
                <form wire:submit="recordRepair" class="mt-4 space-y-3 border-t border-line pt-4">
                    <label for="repairLineId" class="field-label">Хийсэн ажил</label>
                    <select id="repairLineId" wire:model="repairLineId" class="field-input">
                        <option value="">Сонгох…</option>
                        @foreach ($order->currentApprovedRevision->lines as $line)
                            <option value="{{ $line->id }}">{{ $line->description }}</option>
                        @endforeach
                    </select>
                    @error('repairLineId') <p class="field-error">{{ $message }}</p> @enderror
                    <label for="repairWork" class="field-label">Тэмдэглэл</label>
                    <textarea id="repairWork" wire:model="repairWork" rows="2" class="field-input"></textarea>
                    <button type="submit" class="btn-secondary min-h-12 w-full">Хийсэн гэж бүртгэх</button>
                </form>
            @endif
            <p class="mt-3 text-sm text-muted">Хүрээнээс гадуурх нэмэлт ажил гарвал зөвлөхөд мэдэгдэнэ — шинэ үнийн саналаар зөвшөөрүүлнэ.</p>
        </section>
    @endif

    {{-- Inspection --}}
    <section class="card" aria-labelledby="insp-h">
        <h2 id="insp-h" class="text-lg">Үзлэг</h2>
        @foreach ($order->inspectionItems->sortBy('sort_order') as $item)
            <div class="mt-3 border-t border-line pt-3 first:border-0" wire:key="item-{{ $item->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold">{{ $item->label }}</span>
                    <span class="flex items-center gap-2"><x-status-badge :status="$item->condition" />@if ($item->published_at)<span class="badge bg-primary-soft text-primary">Нийтэлсэн</span>@endif</span>
                </div>
                @if ($item->finding_internal)<p class="text-sm"><span class="text-muted">Дотоод:</span> {{ $item->finding_internal }}</p>@endif
                @if ($item->finding_customer)<p class="text-sm"><span class="text-muted">Хэрэглэгчид:</span> {{ $item->finding_customer }}</p>@endif
                <button type="button" wire:click="editItem({{ $item->id }})" class="mt-1 min-h-11 text-sm text-primary underline">Засах</button>
            </div>
        @endforeach

        <form wire:submit="saveItem" class="mt-4 space-y-3 border-t border-line pt-4">
            <h3 class="font-semibold">{{ $editingItemId ? 'Мөр засах' : 'Шинэ мөр' }}</h3>
            <div>
                <label for="itemLabel" class="field-label">Шалгасан хэсэг</label>
                <input id="itemLabel" type="text" wire:model="itemLabel" list="inspection-templates" class="field-input">
                <datalist id="inspection-templates">
                    @foreach (['Тоормосны дэвсгэр', 'Тоормосны шингэн', 'Хөдөлгүүрийн тос', 'Хөргөлтийн шингэн', 'Дугуй, хий', 'Гэрэл', 'Явах эд анги', 'Аккумлятор'] as $template)<option value="{{ $template }}">@endforeach
                </datalist>
                @error('itemLabel') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <fieldset>
                <legend class="field-label">Нөхцөл</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (\App\Enums\InspectionCondition::cases() as $condition)
                        <label class="flex min-h-12 items-center gap-2 rounded-[8px] border px-3 {{ $itemCondition === $condition->value ? 'border-primary bg-primary-soft' : 'border-line' }}">
                            <input type="radio" wire:model.live="itemCondition" value="{{ $condition->value }}" class="size-5 accent-[#0B5A60]"> {{ $condition->getLabel() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div>
                <label for="itemInternal" class="field-label">Дотоод тэмдэглэл (хэрэглэгчид харагдахгүй)</label>
                <textarea id="itemInternal" wire:model="itemInternal" rows="2" class="field-input"></textarea>
            </div>
            <div>
                <label for="itemCustomer" class="field-label">Хэрэглэгчид зориулсан тайлбар</label>
                <textarea id="itemCustomer" wire:model="itemCustomer" rows="2" class="field-input"></textarea>
                <p class="field-hint">Зөвлөх хянаж нийтэлсний дараа хэрэглэгчид харагдана.</p>
            </div>
            <button type="submit" class="btn-primary min-h-12 w-full">Хадгалах</button>
        </form>
    </section>

    {{-- Photos --}}
    <section class="card" aria-labelledby="photo-h" x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false">
        <h2 id="photo-h" class="text-lg">Зураг</h2>
        <form wire:submit="uploadPhoto" class="mt-3 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <label class="btn-secondary min-h-12 cursor-pointer">Зураг авах<input type="file" accept="image/*" capture="environment" wire:model="photo" class="sr-only"></label>
                <label class="btn-secondary min-h-12 cursor-pointer">Галерей<input type="file" accept="image/jpeg,image/png,image/webp" wire:model="photo" class="sr-only"></label>
            </div>
            <p x-show="uploading" x-cloak class="text-sm text-muted" role="status">Байршуулж байна…</p>
            @if ($photo && method_exists($photo, 'isPreviewable') && $photo->isPreviewable())
                <img src="{{ $photo->temporaryUrl() }}" alt="Сонгосон зураг" class="max-h-48 rounded-[8px] border border-line">
            @endif
            @error('photo') <p class="field-error">{{ $message }}</p> @enderror
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="photoCategory" class="field-label">Ангилал</label>
                    <select id="photoCategory" wire:model="photoCategory" class="field-input">
                        @foreach ([\App\Enums\MediaCategory::Intake, \App\Enums\MediaCategory::Before, \App\Enums\MediaCategory::During, \App\Enums\MediaCategory::After] as $category)
                            <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="photoItemId" class="field-label">Үзлэгийн мөр</label>
                    <select id="photoItemId" wire:model="photoItemId" class="field-input">
                        <option value="">—</option>
                        @foreach ($order->inspectionItems as $item)<option value="{{ $item->id }}">{{ $item->label }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label for="photoCaption" class="field-label">Тайлбар</label>
                <input id="photoCaption" type="text" wire:model="photoCaption" class="field-input">
            </div>
            <button type="submit" class="btn-primary min-h-12 w-full" x-bind:disabled="uploading">Зураг хадгалах</button>
        </form>
        <ul class="mt-4 grid grid-cols-3 gap-2">
            @foreach ($media as $file)
                <li wire:key="media-{{ $file->id }}">
                    <a href="{{ route('media.show', [$file, 'full']) }}" target="_blank"><img src="{{ route('media.show', [$file, 'thumb']) }}" alt="{{ $file->caption ?: $file->category->getLabel() }}" loading="lazy" class="aspect-square w-full rounded-[8px] object-cover"></a>
                    <p class="text-xs text-muted">{{ $file->category->getLabel() }}{{ $file->customer_visible ? ' · хэрэглэгчид' : '' }}</p>
                </li>
            @endforeach
        </ul>
    </section>
</div>
