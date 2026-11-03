<div x-data="{ uploading: false, progress: 0 }"
     x-on:livewire-upload-start="uploading = true; progress = 0"
     x-on:livewire-upload-finish="uploading = false"
     x-on:livewire-upload-cancel="uploading = false"
     x-on:livewire-upload-error="uploading = false"
     x-on:livewire-upload-progress="progress = $event.detail.progress"
     x-on:step-changed.window="$nextTick(() => document.getElementById('wizard-heading')?.focus())">

    <ol class="mb-6 grid grid-cols-3 gap-2" aria-label="Явц">
        @foreach ([1 => 'Тусламж', 2 => 'Машин ба холбоо', 3 => 'Хянах'] as $number => $label)
            <li class="rounded-[8px] border px-3 py-2 text-sm {{ $step === $number ? 'border-primary bg-primary-soft font-semibold text-primary' : ($step > $number ? 'border-line bg-surface text-ink' : 'border-line bg-surface text-muted') }}"
                @if ($step === $number) aria-current="step" @endif>
                <span class="block text-xs">{{ $number }}/3</span>{{ $label }}
            </li>
        @endforeach
    </ol>
    <p class="sr-only" aria-live="polite">Алхам {{ $step }} / 3</p>

    @if ($errors->any())
        <div class="mb-6 rounded-[12px] border border-danger bg-danger-soft p-4" role="alert" x-init="$el.focus()" tabindex="-1">
            <p class="font-semibold text-danger">Дараах зүйлсийг засна уу:</p>
            <ul class="mt-2 list-disc pl-5 text-sm text-danger">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="{{ $step === 3 ? 'submit' : 'next' }}" class="card space-y-6" novalidate>
        <div class="hidden" aria-hidden="true">
            <label>Вэбсайт <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
        </div>

        @if ($step === 1)
            <h2 id="wizard-heading" tabindex="-1" class="text-2xl">Ямар тусламж хэрэгтэй вэ?</h2>

            <fieldset>
                <legend class="field-label">Хүсэлтийн төрөл</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-3">
                    @foreach (\App\Enums\RequestType::cases() as $type)
                        @continue($type === \App\Enums\RequestType::Callout && ! $calloutEnabled)
                        <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-[8px] border p-3 {{ $requestType === $type->value ? 'border-primary bg-primary-soft' : 'border-line' }}">
                            <input type="radio" wire:model.live="requestType" value="{{ $type->value }}" class="mt-1 size-5 accent-[#0B5A60]">
                            <span>
                                <span class="font-semibold">{{ $type->getLabel() }}</span>
                                <span class="block text-sm text-muted">
                                    @switch($type)
                                        @case(\App\Enums\RequestType::Workshop) Машинаа сервис дээр авчирна. @break
                                        @case(\App\Enums\RequestType::Callback) Ажилтан танд эргэж залгана (автомат дуудлага биш). @break
                                        @case(\App\Enums\RequestType::Callout) Ажилтан очиж үзэх боломжийг тохирно. Шууд илгээгдсэн гэсэн үг биш. @break
                                    @endswitch
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('requestType') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>

            <div>
                <label for="serviceId" class="field-label">Үйлчилгээ</label>
                <select id="serviceId" wire:model="serviceId" class="field-input">
                    <option value="">Би сайн мэдэхгүй</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name_mn }}</option>
                    @endforeach
                </select>
                @error('serviceId') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="symptoms" class="field-label">Асуудлаа бичнэ үү</label>
                <textarea id="symptoms" wire:model.blur="symptoms" rows="4" class="field-input" aria-describedby="symptoms-hint @error('symptoms') symptoms-error @enderror"
                          @error('symptoms') aria-invalid="true" @enderror></textarea>
                <p id="symptoms-hint" class="field-hint">Жишээ: «Тоормослоход шажигнана», «хөдөлгүүрийн гэрэл асав».</p>
                @error('symptoms') <p id="symptoms-error" class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <p class="field-label">Зураг (заавал биш, 5 хүртэл)</p>
                <p class="field-hint">Зураг нь асуудлыг ойлгоход тусална — онош биш. JPEG, PNG, WebP, тус бүр 8 MB хүртэл.</p>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                    <label class="btn-secondary cursor-pointer">
                        <span>Зураг авах</span>
                        <input type="file" accept="image/*" capture="environment" wire:model="newPhotos" class="sr-only">
                    </label>
                    <label class="btn-secondary cursor-pointer">
                        <span>Галерейгаас сонгох</span>
                        <input type="file" accept="image/jpeg,image/png,image/webp" multiple wire:model="newPhotos" class="sr-only">
                    </label>
                </div>
                <div x-show="uploading" x-cloak class="mt-3" role="status" aria-live="polite">
                    <div class="h-2 w-full overflow-hidden rounded bg-line"><div class="h-2 bg-primary" :style="`width: ${progress}%`"></div></div>
                    <p class="mt-1 text-sm text-muted">Зураг байршуулж байна… <span x-text="progress"></span>%</p>
                </div>
                @error('newPhotos') <p class="field-error">{{ $message }}</p> @enderror
                @error('newPhotos.*') <p class="field-error">{{ $message }}</p> @enderror

                @if ($photos)
                    <ul class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
                        @foreach ($photos as $index => $photo)
                            <li class="relative" wire:key="photo-{{ $index }}-{{ $photo->getFilename() }}">
                                @if ($photo->isPreviewable())
                                    <img src="{{ $photo->temporaryUrl() }}" alt="Сонгосон зураг {{ $index + 1 }}" class="aspect-square w-full rounded-[8px] border border-line object-cover" width="120" height="120">
                                @else
                                    <div class="flex aspect-square w-full items-center justify-center rounded-[8px] border border-line bg-canvas text-sm text-muted">Зураг {{ $index + 1 }}</div>
                                @endif
                                <button type="button" wire:click="removePhoto({{ $index }})" class="mt-1 min-h-11 w-full rounded-[8px] border border-line bg-surface text-sm text-danger">Хасах</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @elseif ($step === 2)
            <h2 id="wizard-heading" tabindex="-1" class="text-2xl">Машин ба холбоо барих</h2>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="name" class="field-label">Таны нэр</label>
                    <input id="name" type="text" wire:model.blur="name" autocomplete="name" class="field-input" @error('name') aria-invalid="true" @enderror>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="field-label">Утасны дугаар</label>
                    <input id="phone" type="tel" inputmode="tel" wire:model.blur="phone" autocomplete="tel" placeholder="9911 2233" class="field-input" aria-describedby="phone-hint" @error('phone') aria-invalid="true" @enderror>
                    <p id="phone-hint" class="field-hint">8 оронтой дугаар (+976 байж болно).</p>
                    @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="vehicleText" class="field-label">Машины марк, загвар</label>
                    <input id="vehicleText" type="text" wire:model.blur="vehicleText" placeholder="Жишээ: Toyota Prius 30" class="field-input" @error('vehicleText') aria-invalid="true" @enderror>
                    @error('vehicleText') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="plate" class="field-label">Улсын дугаар <span class="font-normal text-muted">(заавал биш)</span></label>
                        <input id="plate" type="text" wire:model.blur="plate" autocapitalize="characters" class="field-input">
                        @error('plate') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="vehicleYear" class="field-label">Он <span class="font-normal text-muted">(заавал биш)</span></label>
                        <input id="vehicleYear" type="text" inputmode="numeric" wire:model.blur="vehicleYear" class="field-input">
                        @error('vehicleYear') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="preferredDate" class="field-label">Хүссэн өдөр <span class="font-normal text-muted">(заавал биш)</span></label>
                    <input id="preferredDate" type="date" min="{{ $minDate }}" wire:model.blur="preferredDate" class="field-input">
                    @error('preferredDate') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="preferredWindow" class="field-label">Хүссэн цаг</label>
                    <select id="preferredWindow" wire:model="preferredWindow" class="field-input">
                        @foreach (\App\Enums\PreferredWindow::cases() as $window)
                            <option value="{{ $window->value }}">{{ $window->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($requestType === 'callout')
                <div class="rounded-[12px] border border-line bg-canvas p-4">
                    <h3 class="text-lg">Очих хаяг</h3>
                    @if ($calloutArea)<p class="text-sm text-muted">Үйлчлэх хүрээ: {{ $calloutArea }}</p>@endif
                    @if ($calloutFee)<p class="text-sm text-muted">Төлбөр: {{ $calloutFee }}</p>@endif
                    <label for="calloutAddress" class="field-label mt-3">Хаяг, тэмдэг</label>
                    <textarea id="calloutAddress" wire:model.blur="calloutAddress" rows="2" class="field-input"></textarea>
                    @error('calloutAddress') <p class="field-error">{{ $message }}</p> @enderror
                    <div x-data="{ state: '' }" class="mt-3">
                        <button type="button" class="btn-secondary"
                                @click="if (!navigator.geolocation) { state = 'Төхөөрөмж байршил дэмжихгүй байна.'; return; } state = 'Байршил авч байна…'; navigator.geolocation.getCurrentPosition(p => { $wire.set('calloutLat', p.coords.latitude); $wire.set('calloutLng', p.coords.longitude); state = 'Байршил нэг удаа хавсаргалаа.'; }, () => state = 'Байршил авах зөвшөөрөл өгөөгүй.', { timeout: 10000 })">
                            Одоогийн байршлаа нэг удаа хавсаргах
                        </button>
                        <p class="field-hint" x-text="state" aria-live="polite"></p>
                        <p class="field-hint">Бид таны байршлыг тасралтгүй хянахгүй.</p>
                    </div>
                </div>
            @endif
        @else
            <h2 id="wizard-heading" tabindex="-1" class="text-2xl">Хянах ба илгээх</h2>
            <dl class="grid gap-3 rounded-[12px] bg-canvas p-4 sm:grid-cols-2">
                <div><dt class="text-sm text-muted">Төрөл</dt><dd>{{ \App\Enums\RequestType::from($requestType)->getLabel() }}</dd></div>
                <div><dt class="text-sm text-muted">Үйлчилгээ</dt><dd>{{ $services->firstWhere('id', (int) $serviceId)?->name_mn ?? 'Би сайн мэдэхгүй' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-muted">Асуудал</dt><dd class="whitespace-pre-line">{{ $symptoms }}</dd></div>
                <div><dt class="text-sm text-muted">Нэр / утас</dt><dd>{{ $name }} · {{ $phone }}</dd></div>
                <div><dt class="text-sm text-muted">Машин</dt><dd>{{ $vehicleText }} {{ $plate }}</dd></div>
                <div><dt class="text-sm text-muted">Хүссэн цаг</dt><dd>{{ $preferredDate ?: 'Тохирох үед' }} · {{ \App\Enums\PreferredWindow::tryFrom($preferredWindow)?->getLabel() }}</dd></div>
                <div><dt class="text-sm text-muted">Зураг</dt><dd>{{ count($photos) }}</dd></div>
            </dl>

            <div class="rounded-[12px] border border-primary/30 bg-primary-soft p-4 text-sm">
                Энэ нь цаг захиалга биш. Ажилтан тантай холбогдож боломжит цаг болон үнийг баталгаажуулна.
            </div>

            <div class="space-y-3">
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="contactConsent" class="mt-1 size-5 accent-[#0B5A60]" @error('contactConsent') aria-invalid="true" @enderror>
                    <span>Энэ хүсэлтийн талаар АНГАА АВТО надтай утсаар холбогдохыг зөвшөөрч байна. <a href="{{ route('privacy') }}" class="text-primary underline" target="_blank">Хувийн мэдээллийн бодлого</a></span>
                </label>
                @error('contactConsent') <p class="field-error">{{ $message }}</p> @enderror
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="reminderPreference" class="mt-1 size-5 accent-[#0B5A60]">
                    <span>Дараагийн үйлчилгээний сануулга авахыг хүсч байна (заавал биш, дараа нь өөрчилж болно).</span>
                </label>
            </div>
        @endif

        @error('form') <p class="rounded-[8px] bg-danger-soft p-3 font-medium text-danger" role="alert">{{ $message }}</p> @enderror

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-5 sm:flex-row sm:justify-between">
            @if ($step > 1)
                <button type="button" wire:click="back" class="btn-secondary">Буцах</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" x-bind:disabled="uploading">
                <span wire:loading.remove wire:target="next,submit">{{ $step === 3 ? 'Хүсэлт илгээх' : 'Үргэлжлүүлэх' }}</span>
                <span wire:loading wire:target="next,submit">Түр хүлээнэ үү…</span>
            </button>
        </div>
    </form>
</div>
