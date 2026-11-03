<?php

namespace App\Actions\Maintenance;

use App\Enums\ConsentPurpose;
use App\Models\CommunicationConsent;
use App\Models\Customer;
use App\Support\Audit;
use App\Support\DomainException;

/**
 * Explicit, purpose-specific consent bound to a phone number. Marketing is never on by default.
 * Revocation cancels pending messages for that purpose.
 */
class ConsentService
{
    public function __construct(private CancelPendingNotifications $cancel) {}

    public function grant(Customer $customer, ConsentPurpose $purpose, string $source, ?int $actorId = null): CommunicationConsent
    {
        if (! $customer->normalized_contact_phone) {
            throw new DomainException('Хэрэглэгчийн утасны дугаар бүртгэгдээгүй байна.');
        }

        $current = $this->current($customer, $purpose);

        if ($current) {
            return $current;
        }

        $consent = CommunicationConsent::query()->create([
            'customer_id' => $customer->id,
            'phone_normalized' => $customer->normalized_contact_phone,
            'purpose' => $purpose,
            'channel' => 'sms',
            'granted_at' => now(),
            'source' => $source,
            'policy_version' => (string) config('angaa.consent.reminder_version'),
        ]);

        Audit::record('consent.granted', $customer, ['purpose' => $purpose->value, 'source' => $source], actorId: $actorId);

        return $consent;
    }

    public function revoke(Customer $customer, ConsentPurpose $purpose, string $source, ?int $actorId = null): void
    {
        $updated = CommunicationConsent::query()
            ->where('customer_id', $customer->id)
            ->where('purpose', $purpose)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($updated > 0) {
            Audit::record('consent.revoked', $customer, ['purpose' => $purpose->value, 'source' => $source], actorId: $actorId);
        }

        if ($purpose === ConsentPurpose::ServiceReminder) {
            $this->cancel->forCustomer($customer, 'consent_revoked');
        }
    }

    /**
     * Active consent only counts when bound to the customer's current phone number.
     */
    public function current(Customer $customer, ConsentPurpose $purpose): ?CommunicationConsent
    {
        return CommunicationConsent::query()
            ->where('customer_id', $customer->id)
            ->where('purpose', $purpose)
            ->where('phone_normalized', $customer->normalized_contact_phone)
            ->whereNotNull('granted_at')
            ->whereNull('revoked_at')
            ->latest('granted_at')
            ->first();
    }

    public function has(Customer $customer, ConsentPurpose $purpose): bool
    {
        return $this->current($customer, $purpose) !== null;
    }
}
