<?php

namespace App\Actions\Requests;

use App\Enums\MediaCategory;
use App\Enums\PreferredWindow;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\MediaFile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewServiceRequest;
use App\Services\Media\ImageProcessor;
use App\Support\DomainException;
use App\Support\Phone;
use App\Support\Plate;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates a pending public request. It is never a confirmed appointment and is never linked to an
 * existing customer or vehicle on the basis of the submitted (unverified) phone or plate.
 *
 * Idempotent per (guest session, idempotency key): a double submit returns the first request.
 */
class SubmitServiceRequest
{
    public function __construct(private ImageProcessor $images) {}

    /**
     * @param  array{request_type: string, service_id?: int|null, name: string, phone: string, vehicle_text: string, plate?: string|null, vehicle_year?: int|null, symptoms: string, preferred_local_date?: string|null, preferred_window?: string|null, callout_address?: string|null, callout_lat?: float|null, callout_lng?: float|null, reminder_preference?: bool}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function handle(array $data, string $sessionId, string $idempotencyKey, array $photos = []): ServiceRequest
    {
        $sessionHash = hash('sha256', $sessionId);

        $existing = $this->existing($sessionHash, $idempotencyKey);

        if ($existing) {
            return $existing;
        }

        if (! Settings::get('public_booking_enabled')) {
            throw new DomainException('Онлайн хүсэлт түр хаалттай байна. Утсаар холбогдоно уу.');
        }

        $phone = Phone::normalize($data['phone']);

        if ($phone === null) {
            throw new DomainException('Утасны дугаар буруу байна. 8 оронтой дугаар оруулна уу.');
        }

        $type = RequestType::from($data['request_type']);

        if ($type === RequestType::Callout && ! Settings::get('callout_enabled')) {
            throw new DomainException('Очиж үйлчлэх хүсэлт одоогоор хүлээн авахгүй байна.');
        }

        if (count($photos) > (int) config('angaa.uploads.max_files_per_request')) {
            throw new DomainException('Нэг хүсэлтэд 5 хүртэл зураг илгээнэ.');
        }

        $stored = [];

        try {
            foreach ($photos as $photo) {
                $stored[] = $this->images->store($photo);
            }

            $request = DB::transaction(function () use ($data, $sessionHash, $idempotencyKey, $phone, $type, $stored): ServiceRequest {
                $request = ServiceRequest::query()->create([
                    'reference' => $this->reference(),
                    'guest_session_hash' => $sessionHash,
                    'idempotency_key' => $idempotencyKey,
                    'request_type' => $type,
                    'service_id' => $data['service_id'] ?? null,
                    'name' => trim($data['name']),
                    'phone_display' => Phone::display($phone),
                    'phone_normalized' => $phone,
                    'vehicle_text' => trim($data['vehicle_text']),
                    'plate_display' => Plate::display($data['plate'] ?? null),
                    'plate_normalized' => Plate::normalize($data['plate'] ?? null),
                    'vehicle_year' => $data['vehicle_year'] ?? null,
                    'symptoms' => trim($data['symptoms']),
                    'preferred_local_date' => $data['preferred_local_date'] ?? null,
                    'preferred_window' => isset($data['preferred_window']) ? PreferredWindow::from($data['preferred_window']) : null,
                    'callout_address' => $type === RequestType::Callout ? ($data['callout_address'] ?? null) : null,
                    'callout_lat' => $type === RequestType::Callout ? ($data['callout_lat'] ?? null) : null,
                    'callout_lng' => $type === RequestType::Callout ? ($data['callout_lng'] ?? null) : null,
                    'contact_consent_at' => now(),
                    'contact_consent_version' => (string) config('angaa.consent.contact_version'),
                    'reminder_preference' => (bool) ($data['reminder_preference'] ?? false),
                    'status' => RequestStatus::New,
                ]);

                foreach ($stored as $attributes) {
                    MediaFile::query()->create($attributes + [
                        'service_request_id' => $request->id,
                        'upload_session_hash' => $sessionHash,
                        'upload_idempotency_key' => $idempotencyKey,
                        'category' => MediaCategory::Request,
                        'customer_visible' => false,
                    ]);
                }

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            $this->discard($stored);

            return $this->existing($sessionHash, $idempotencyKey) ?? throw new DomainException('Хүсэлт илгээхэд алдаа гарлаа. Дахин оролдоно уу.');
        } catch (\Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        DB::afterCommit(fn () => $this->notifyStaff($request));

        return $request;
    }

    private function existing(string $sessionHash, string $idempotencyKey): ?ServiceRequest
    {
        return ServiceRequest::query()
            ->where('guest_session_hash', $sessionHash)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Short random reference shown on the receipt; not a credential.
     */
    private function reference(): string
    {
        do {
            $reference = 'H-'.Str::upper(Str::random(6));
        } while (ServiceRequest::query()->where('reference', $reference)->exists());

        return strtr($reference, ['0' => 'X', 'O' => 'Y', 'I' => 'Z', '1' => 'W', 'L' => 'Q']);
    }

    /**
     * @param  list<array{disk: string, private_path: string, preview_path: string}>  $stored
     */
    private function discard(array $stored): void
    {
        foreach ($stored as $file) {
            Storage::disk($file['disk'])->delete([$file['private_path'], $file['preview_path']]);
        }
    }

    private function notifyStaff(ServiceRequest $request): void
    {
        $recipients = User::query()->where('type', 'staff')->where('active', true)->whereIn('staff_role', ['owner', 'advisor'])->get();

        Notification::send($recipients, new NewServiceRequest($request));
    }
}
