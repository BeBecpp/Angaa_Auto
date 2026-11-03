<?php

namespace App\Actions\Auth;

use App\Contracts\SmsProvider;
use App\Enums\UserType;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\DomainException;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Customer phone sign-in. Codes are hashed, session-bound, expiring, attempt-capped and rate limited.
 * Errors are generic. A customer identity can never reach the staff panel.
 *
 * The local OTP viewer value is stored only in local/testing environments.
 */
class OtpService
{
    public function __construct(private SmsProvider $sms) {}

    public function portalAvailable(): bool
    {
        return $this->sms->isAvailable();
    }

    public function request(string $phoneInput, string $sessionId, string $ip): OtpChallenge
    {
        $phone = Phone::normalize($phoneInput);

        if ($phone === null) {
            throw new DomainException('Утасны дугаар буруу байна. 8 оронтой дугаар оруулна уу.');
        }

        if (! $this->portalAvailable()) {
            throw new DomainException('Утсаар нэвтрэх үйлчилгээ одоогоор идэвхгүй байна. Сервис рүү холбогдоно уу.');
        }

        foreach (['otp-phone:'.Phone::hash($phone) => 5, 'otp-ip:'.$ip => 20] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new DomainException('Хэт олон оролдлого. '.ceil(RateLimiter::availableIn($key) / 60).' минутын дараа дахин оролдоно уу.');
            }
        }

        $binding = hash('sha256', $sessionId);

        $recent = OtpChallenge::query()
            ->where('phone_normalized', $phone)
            ->where('session_binding', $binding)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($recent && $recent->resend_available_at->isFuture()) {
            throw new DomainException('Код дахин авахын тулд '.now()->diffInSeconds($recent->resend_available_at, true).' секунд хүлээнэ үү.');
        }

        RateLimiter::hit('otp-phone:'.Phone::hash($phone), 3600);
        RateLimiter::hit('otp-ip:'.$ip, 3600);

        OtpChallenge::query()->where('session_binding', $binding)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = OtpChallenge::query()->create([
            'phone_normalized' => $phone,
            'hashed_code' => Hash::make($code),
            'session_binding' => $binding,
            'expires_at' => now()->addMinutes((int) config('angaa.otp.ttl_minutes')),
            'max_attempts' => (int) config('angaa.otp.max_attempts'),
            'resend_available_at' => now()->addSeconds((int) config('angaa.otp.resend_cooldown_seconds')),
            'local_preview_code' => $this->localViewerEnabled() ? $code : null,
        ]);

        $this->sms->send($phone, config('angaa.brand.name').': нэвтрэх код '.$code.'. Хэнд ч бүү хэл.', 'otp:'.$challenge->id);

        return $challenge;
    }

    public function verify(string $phoneInput, string $code, string $sessionId): User
    {
        $phone = Phone::normalize($phoneInput);
        $generic = 'Код буруу эсвэл хугацаа дууссан байна.';

        if ($phone === null || ! preg_match('/^\d{6}$/', $code)) {
            throw new DomainException($generic);
        }

        $challenge = OtpChallenge::query()
            ->where('phone_normalized', $phone)
            ->where('session_binding', hash('sha256', $sessionId))
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $challenge || $challenge->expires_at->isPast() || $challenge->attempt_count >= $challenge->max_attempts) {
            throw new DomainException($generic);
        }

        $challenge->increment('attempt_count');

        if (! Hash::check($code, $challenge->hashed_code)) {
            throw new DomainException($generic);
        }

        $challenge->forceFill(['consumed_at' => now(), 'local_preview_code' => null])->save();

        $user = User::query()->where('phone_normalized', $phone)->first();

        if ($user && ! $user->isCustomer()) {
            throw new DomainException($generic);
        }

        if (! $user) {
            $user = new User(['name' => 'Хэрэглэгч '.substr($phone, -4)]);
            $user->forceFill(['type' => UserType::Customer, 'phone_normalized' => $phone, 'active' => true]);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        if (! $user->active) {
            throw new DomainException($generic);
        }

        return $user;
    }

    public function localViewerEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) config('angaa.otp.local_viewer');
    }

    /**
     * Latest unconsumed code for THIS session's challenge only (local/testing viewer).
     */
    public function localPreview(string $sessionId): ?string
    {
        if (! $this->localViewerEnabled()) {
            return null;
        }

        return OtpChallenge::query()
            ->where('session_binding', hash('sha256', $sessionId))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->value('local_preview_code');
    }

    public static function purgeExpired(): int
    {
        return OtpChallenge::query()->where('expires_at', '<', now()->subDay())->delete();
    }
}
