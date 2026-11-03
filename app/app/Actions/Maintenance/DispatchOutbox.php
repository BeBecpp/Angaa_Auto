<?php

namespace App\Actions\Maintenance;

use App\Contracts\SmsProvider;
use App\Enums\AppointmentStatus;
use App\Enums\ConsentPurpose;
use App\Enums\OutboxStatus;
use App\Enums\PlanStatus;
use App\Models\Appointment;
use App\Models\MaintenancePlan;
use App\Models\MessageAttempt;
use App\Models\NotificationOutbox;
use App\Services\Messaging\SmsResult;
use App\Support\BusinessTime;
use App\Support\Phone;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Claims due outbox rows atomically (one worker per row), re-checks everything immediately before
 * sending, and records a truthful outcome. Exactly-once network delivery is not promised; unknown
 * outcomes go to reconciliation instead of a blind retry.
 */
class DispatchOutbox
{
    public function __construct(
        private SmsProvider $sms,
        private QuietHours $quietHours,
        private ConsentService $consents,
        private PlanAppointmentNotifications $appointmentRules,
        private EvaluateDuePlans $planRules,
    ) {}

    /**
     * @return array<string, int> counts by resulting status
     */
    public function dispatchDue(?CarbonImmutable $now = null, int $limit = 50): array
    {
        $now ??= CarbonImmutable::now();
        $this->reconcileStuck($now);

        $ids = NotificationOutbox::query()
            ->whereIn('status', [OutboxStatus::Scheduled, OutboxStatus::Queued])
            ->where('scheduled_for', '<=', $now)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->pluck('id');

        $counts = [];

        foreach ($ids as $id) {
            $status = $this->dispatchOne($id, $now);

            if ($status !== null) {
                $counts[$status->value] = ($counts[$status->value] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Atomic claim: only the worker whose conditional UPDATE affects the row may send it.
     */
    public function claim(int $id, CarbonImmutable $now): ?string
    {
        $token = (string) Str::uuid();

        $claimed = NotificationOutbox::query()
            ->whereKey($id)
            ->whereIn('status', [OutboxStatus::Scheduled->value, OutboxStatus::Queued->value])
            ->update(['status' => OutboxStatus::Dispatching, 'claim_token' => $token, 'claimed_at' => $now, 'updated_at' => $now]);

        return $claimed === 1 ? $token : null;
    }

    public function dispatchOne(int $id, CarbonImmutable $now): ?OutboxStatus
    {
        $token = $this->claim($id, $now);

        if ($token === null) {
            return null;
        }

        $message = NotificationOutbox::query()->whereKey($id)->where('claim_token', $token)->firstOrFail();

        $skip = $this->skipReason($message, $now);

        if ($skip !== null) {
            $message->forceFill(['status' => OutboxStatus::Skipped, 'skip_reason' => $skip])->save();

            return OutboxStatus::Skipped;
        }

        if ($this->quietHours->isQuiet($now)) {
            $next = $this->quietHours->nextAllowed($now);

            if ($message->expires_at && $next->greaterThan($message->expires_at)) {
                $message->forceFill(['status' => OutboxStatus::Skipped, 'skip_reason' => 'quiet_hours_no_longer_useful'])->save();

                return OutboxStatus::Skipped;
            }

            $message->forceFill(['status' => OutboxStatus::Scheduled, 'scheduled_for' => $next, 'claim_token' => null])->save();

            return OutboxStatus::Scheduled;
        }

        if ($this->overDailyCap($message, $now)) {
            $next = $this->quietHours->nextAllowed(BusinessTime::local($now)->addDay()->setTime(10, 0));
            $message->forceFill(['status' => OutboxStatus::Scheduled, 'scheduled_for' => $next, 'claim_token' => null, 'skip_reason' => 'deferred_daily_cap'])->save();

            return OutboxStatus::Scheduled;
        }

        return $this->send($message, $now);
    }

    private function send(NotificationOutbox $message, CarbonImmutable $now): OutboxStatus
    {
        $attemptNumber = $message->attempt_count + 1;
        $attempt = MessageAttempt::query()->create([
            'notification_outbox_id' => $message->id,
            'attempt_number' => $attemptNumber,
            'started_at' => $now,
            'adapter' => $this->sms->name(),
            'status' => 'started',
        ]);

        try {
            $result = $this->sms->send($message->recipient_phone, $this->render($message), $message->idempotency_key);
        } catch (Throwable) {
            $result = SmsResult::unknown('transport_exception');
        }

        $status = match ($result->outcome) {
            SmsResult::SIMULATED => OutboxStatus::Simulated,
            SmsResult::ACCEPTED => OutboxStatus::ProviderAccepted,
            SmsResult::UNKNOWN => OutboxStatus::Unknown,
            default => $result->retryable && $attemptNumber < (int) config('angaa.reminders.max_attempts') ? OutboxStatus::Scheduled : OutboxStatus::Failed,
        };

        $attempt->forceFill([
            'finished_at' => now(),
            'provider_reference' => $result->providerReference,
            'status' => $result->outcome,
            'normalized_error' => $result->errorCode,
        ])->save();

        $message->forceFill([
            'status' => $status,
            'attempt_count' => $attemptNumber,
            'provider_message_id' => $result->providerReference,
            'accepted_at' => $status === OutboxStatus::ProviderAccepted ? now() : $message->accepted_at,
            'last_error_code' => $result->errorCode,
            'next_attempt_at' => $status === OutboxStatus::Scheduled ? $now->addMinutes(5 * (2 ** $attemptNumber)) : null,
            'claim_token' => null,
        ])->save();

        return $status;
    }

    /**
     * Re-check consent, recipient, ownership, aggregate version/state and expiry right before sending.
     */
    public function skipReason(NotificationOutbox $message, CarbonImmutable $now): ?string
    {
        if ($message->expires_at && $now->greaterThan($message->expires_at)) {
            return 'expired';
        }

        $customer = $message->customer;

        if ($customer === null || $customer->archived_at !== null) {
            return 'customer_unavailable';
        }

        if ($customer->normalized_contact_phone !== $message->recipient_phone) {
            return 'recipient_changed';
        }

        if ($message->aggregate_type === 'appointment') {
            $appointment = Appointment::query()->find($message->aggregate_id);

            if (! $appointment || $appointment->status !== AppointmentStatus::Confirmed) {
                return 'appointment_not_confirmed';
            }

            if ($appointment->version !== $message->aggregate_version) {
                return 'appointment_changed';
            }

            if (CarbonImmutable::instance($appointment->planned_start)->lessThanOrEqualTo($now->addMinutes(30))) {
                return 'too_late';
            }

            return $this->appointmentRules->permitted($appointment) ? null : 'no_consent';
        }

        if ($message->aggregate_type === 'maintenance_plan') {
            $plan = MaintenancePlan::query()->find($message->aggregate_id);

            if (! $plan || $plan->status !== PlanStatus::Active) {
                return 'plan_not_active';
            }

            if ($plan->version !== $message->aggregate_version) {
                return 'plan_changed';
            }

            if (! $this->planRules->eligible($plan)) {
                return $this->consents->has($customer, ConsentPurpose::ServiceReminder) ? 'ownership_changed' : 'no_consent';
            }

            return null;
        }

        return 'unknown_aggregate';
    }

    public function render(NotificationOutbox $message): string
    {
        $payload = $message->payload_minimal ?? [];
        $contact = Phone::display(Settings::contactPhone()) ?: 'сервис рүү';
        $brand = (string) config('angaa.brand.name');

        return match ($message->template_key) {
            'appointment_reminder' => "{$brand}: Таны үйлчилгээний цаг {$payload['date']} {$payload['time']}. Өөрчлөх бол {$contact}.",
            'service_due' => "{$brand}: {$payload['vehicle']} — «{$payload['service']}» хийх хугацаа болж байна. Цаг авах бол {$contact}.",
            default => "{$brand}: мэдэгдэл.",
        };
    }

    private function overDailyCap(NotificationOutbox $message, CarbonImmutable $now): bool
    {
        $dayStart = BusinessTime::local($now)->startOfDay()->utc();

        $sentToday = NotificationOutbox::query()
            ->where('customer_id', $message->customer_id)
            ->where('id', '!=', $message->id)
            ->whereIn('status', [OutboxStatus::Simulated, OutboxStatus::ProviderAccepted, OutboxStatus::Delivered, OutboxStatus::Dispatching])
            ->where('updated_at', '>=', $dayStart)
            ->count();

        return $sentToday >= (int) config('angaa.reminders.max_per_customer_per_day');
    }

    /**
     * Rows stuck in "dispatching" (worker died mid-send) become unknown for manual reconciliation.
     */
    private function reconcileStuck(CarbonImmutable $now): void
    {
        NotificationOutbox::query()
            ->where('status', OutboxStatus::Dispatching)
            ->where('claimed_at', '<', $now->subMinutes(10))
            ->update(['status' => OutboxStatus::Unknown, 'last_error_code' => 'worker_timeout', 'claim_token' => null, 'updated_at' => $now]);
    }
}
