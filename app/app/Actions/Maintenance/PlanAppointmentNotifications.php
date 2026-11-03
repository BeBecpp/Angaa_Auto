<?php

namespace App\Actions\Maintenance;

use App\Enums\AppointmentStatus;
use App\Enums\ConsentPurpose;
use App\Models\Appointment;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * Appointment reminders: 24h and 2h before (configurable). Stages already in the past are skipped,
 * so a booking confirmed inside 24h never receives a stale 24h message.
 */
class PlanAppointmentNotifications
{
    public function __construct(private OutboxWriter $writer, private QuietHours $quietHours, private ConsentService $consents) {}

    /**
     * @return int number of messages scheduled
     */
    public function handle(Appointment $appointment, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        if ($appointment->status !== AppointmentStatus::Confirmed || ! $this->permitted($appointment)) {
            return 0;
        }

        $start = CarbonImmutable::instance($appointment->planned_start);
        $latestUseful = $start->subMinutes(30);
        $created = 0;

        foreach (config('angaa.reminders.appointment_offsets_hours') as $hours) {
            $sendAt = $this->quietHours->nextAllowed($start->subHours((int) $hours));

            if ($sendAt->lessThan($now) || $sendAt->greaterThan($latestUseful)) {
                continue;
            }

            $row = $this->writer->schedule(
                'appointment',
                $appointment->id,
                $appointment->version,
                $appointment->customer,
                'appointment_reminder',
                'before_'.$hours.'h',
                $sendAt,
                [
                    'date' => BusinessTime::format($start, 'm/d'),
                    'time' => BusinessTime::format($start, 'H:i'),
                ],
                $latestUseful,
            );

            $created += $row ? 1 : 0;
        }

        return $created;
    }

    /**
     * Reminders about a booked visit are allowed with service-reminder consent, or when the visit came
     * from a request where the customer consented to be contacted about it.
     */
    public function permitted(Appointment $appointment): bool
    {
        $customer = $appointment->customer;

        if ($customer === null || $customer->normalized_contact_phone === null) {
            return false;
        }

        if ($this->consents->has($customer, ConsentPurpose::ServiceReminder)) {
            return true;
        }

        $request = $appointment->serviceRequest;

        $revoked = $customer->consents()->where('purpose', ConsentPurpose::ServiceReminder)->whereNotNull('revoked_at')->exists();

        return $request !== null
            && $request->contact_consent_at !== null
            && $request->phone_normalized === $customer->normalized_contact_phone
            && ! $revoked;
    }
}
