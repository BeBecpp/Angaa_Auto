<?php

namespace App\Livewire;

use App\Actions\Requests\SubmitServiceRequest;
use App\Enums\PreferredWindow;
use App\Enums\RequestType;
use App\Models\Service;
use App\Support\BusinessTime;
use App\Support\DomainException;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Three-step guest request. Field values survive validation errors and step changes; the idempotency
 * key is fixed for this form instance so double submits return the same request.
 */
class RequestWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    #[Locked]
    public string $idempotencyKey = '';

    public string $requestType = 'workshop';

    public string $serviceId = '';

    public string $symptoms = '';

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    /** @var list<TemporaryUploadedFile> */
    public array $newPhotos = [];

    public string $name = '';

    public string $phone = '';

    public string $vehicleText = '';

    public string $plate = '';

    public string $vehicleYear = '';

    public string $preferredDate = '';

    public string $preferredWindow = 'any';

    public string $calloutAddress = '';

    public ?float $calloutLat = null;

    public ?float $calloutLng = null;

    public bool $contactConsent = false;

    public bool $reminderPreference = false;

    /** Honeypot: real users never fill this. */
    public string $website = '';

    public function mount(?string $service = null): void
    {
        $this->idempotencyKey = (string) Str::ulid();

        if ($service) {
            $this->serviceId = (string) (Service::query()->where('slug', $service)->where('published', true)->value('id') ?? '');
        }
    }

    public function updatedNewPhotos(): void
    {
        $this->validate([
            'newPhotos' => ['array'],
            'newPhotos.*' => ['file', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'newPhotos.*.max' => 'Зураг бүр 8 MB-аас ихгүй байна.',
            'newPhotos.*.mimes' => 'Зөвхөн JPEG, PNG, WebP зураг. iPhone-ийн HEIC зургийг «Most Compatible» горимоор авна уу.',
        ]);

        foreach ($this->newPhotos as $photo) {
            if (count($this->photos) >= (int) config('angaa.uploads.max_files_per_request')) {
                $this->addError('newPhotos', 'Нэг хүсэлтэд 5 хүртэл зураг илгээнэ.');

                break;
            }

            $this->photos[] = $photo;
        }

        $this->newPhotos = [];
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function next(): void
    {
        $this->validate($this->rulesForStep($this->step), $this->messages());
        $this->step = min(3, $this->step + 1);
        $this->dispatch('step-changed');
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->dispatch('step-changed');
    }

    public function submit(SubmitServiceRequest $action): mixed
    {
        if ($this->website !== '') {
            return $this->redirectRoute('home');
        }

        $this->validate(array_merge($this->rulesForStep(1), $this->rulesForStep(2), $this->rulesForStep(3)), $this->messages());

        $limiterKey = 'request-submit:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 6)) {
            $this->addError('form', 'Хэт олон хүсэлт илгээсэн байна. Түр хүлээгээд дахин оролдоно уу.');

            return null;
        }

        try {
            $request = $action->handle([
                'request_type' => $this->requestType,
                'service_id' => $this->serviceId !== '' ? (int) $this->serviceId : null,
                'name' => $this->name,
                'phone' => $this->phone,
                'vehicle_text' => $this->vehicleText,
                'plate' => $this->plate ?: null,
                'vehicle_year' => $this->vehicleYear !== '' ? (int) $this->vehicleYear : null,
                'symptoms' => $this->symptoms,
                'preferred_local_date' => $this->preferredDate ?: null,
                'preferred_window' => $this->preferredWindow ?: null,
                'callout_address' => $this->calloutAddress ?: null,
                'callout_lat' => $this->calloutLat,
                'callout_lng' => $this->calloutLng,
                'reminder_preference' => $this->reminderPreference,
            ], session()->getId(), $this->idempotencyKey, $this->photos);
        } catch (DomainException $exception) {
            $this->addError('form', $exception->getMessage());

            return null;
        }

        RateLimiter::hit($limiterKey, 600);

        return $this->redirectRoute('request.receipt', ['reference' => $request->reference]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForStep(int $step): array
    {
        $types = collect(RequestType::cases())->map->value
            ->reject(fn (string $type): bool => $type === RequestType::Callout->value && ! Settings::get('callout_enabled'))
            ->all();

        return match ($step) {
            1 => [
                'requestType' => ['required', Rule::in($types)],
                'serviceId' => ['nullable', Rule::exists('services', 'id')->where('published', true)],
                'symptoms' => ['required', 'string', 'min:5', 'max:2000'],
                'photos' => ['array', 'max:5'],
            ],
            2 => [
                'name' => ['required', 'string', 'max:80'],
                'phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Phone::normalize((string) $value) === null) {
                        $fail('Утасны дугаар буруу байна. 8 оронтой дугаар оруулна уу (жишээ: 9911 2233).');
                    }
                }],
                'vehicleText' => ['required', 'string', 'max:120'],
                'plate' => ['nullable', 'string', 'max:20'],
                'vehicleYear' => ['nullable', 'integer', 'between:1950,'.(BusinessTime::now()->year + 1)],
                'preferredDate' => ['nullable', 'date', 'after_or_equal:'.BusinessTime::today()->toDateString()],
                'preferredWindow' => ['nullable', Rule::enum(PreferredWindow::class)],
                'calloutAddress' => [$this->requestType === RequestType::Callout->value ? 'required' : 'nullable', 'string', 'max:255'],
                'calloutLat' => ['nullable', 'numeric', 'between:-90,90'],
                'calloutLng' => ['nullable', 'numeric', 'between:-180,180'],
            ],
            default => [
                'contactConsent' => ['accepted'],
                'reminderPreference' => ['boolean'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'requestType.required' => 'Хүсэлтийн төрлөө сонгоно уу.',
            'symptoms.required' => 'Ямар асуудал байгааг товч бичнэ үү.',
            'symptoms.min' => 'Асуудлаа арай дэлгэрэнгүй бичнэ үү.',
            'photos.max' => 'Нэг хүсэлтэд 5 хүртэл зураг илгээнэ.',
            'name.required' => 'Нэрээ бичнэ үү.',
            'phone.required' => 'Утасны дугаараа бичнэ үү.',
            'vehicleText.required' => 'Машины марк, загварыг бичнэ үү.',
            'vehicleYear.integer' => 'Он зөвхөн тоо байна.',
            'vehicleYear.between' => 'Үйлдвэрлэсэн он буруу байна.',
            'preferredDate.after_or_equal' => 'Өнгөрсөн өдөр сонгох боломжгүй.',
            'calloutAddress.required' => 'Очих хаягаа бичнэ үү.',
            'contactConsent.accepted' => 'Хүсэлтийн талаар холбогдохыг зөвшөөрнө үү.',
        ];
    }

    public function render(): View
    {
        return view('livewire.request-wizard', [
            'services' => Service::query()->where('published', true)->where('active', true)->orderBy('sort_order')->get(),
            'calloutEnabled' => (bool) Settings::get('callout_enabled'),
            'calloutArea' => Settings::get('callout_area'),
            'calloutFee' => Settings::get('callout_fee_policy'),
            'minDate' => BusinessTime::today()->toDateString(),
        ]);
    }
}
