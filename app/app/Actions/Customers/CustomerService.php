<?php

namespace App\Actions\Customers;

use App\Actions\Maintenance\CancelPendingNotifications;
use App\Enums\VehicleRelationship;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCustomerAccess;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\Phone;
use App\Support\Plate;
use Illuminate\Support\Collection;

/**
 * Staff-controlled identity. Access to vehicles is explicit and revocable; nothing is inferred from a
 * submitted plate or phone. Portal accounts are linked only by staff after phone verification.
 */
class CustomerService
{
    public function __construct(private CancelPendingNotifications $cancel) {}

    /**
     * @param  array{display_name: string, contact_phone?: string|null, notes_internal?: string|null}  $data
     */
    public function createCustomer(array $data, User $actor): Customer
    {
        $this->assertOffice($actor);
        $phone = isset($data['contact_phone']) && $data['contact_phone'] !== '' ? Phone::normalize($data['contact_phone']) : null;

        if (! empty($data['contact_phone']) && $phone === null) {
            throw new DomainException('Утасны дугаар буруу байна.');
        }

        $customer = Customer::query()->create([
            'display_name' => trim($data['display_name']),
            'contact_phone' => $phone ? Phone::display($phone) : null,
            'normalized_contact_phone' => $phone,
            'notes_internal' => $data['notes_internal'] ?? null,
        ]);

        Audit::record('customer.created', $customer, actorId: $actor->id);

        return $customer;
    }

    /**
     * Changing the contact phone stops messages bound to the old number (consent is phone-bound).
     */
    public function changeContactPhone(Customer $customer, string $newPhone, User $actor): Customer
    {
        $this->assertOffice($actor);
        $phone = Phone::normalize($newPhone);

        if ($phone === null) {
            throw new DomainException('Утасны дугаар буруу байна.');
        }

        if ($phone === $customer->normalized_contact_phone) {
            return $customer;
        }

        $customer->forceFill(['contact_phone' => Phone::display($phone), 'normalized_contact_phone' => $phone])->save();
        $this->cancel->forCustomer($customer, 'recipient_changed');
        Audit::record('customer.phone_changed', $customer, actorId: $actor->id);

        return $customer;
    }

    /**
     * @param  array{make: string, model?: string|null, year?: int|null, plate?: string|null, vin?: string|null}  $data
     */
    public function createVehicle(array $data, User $actor): Vehicle
    {
        $this->assertOffice($actor);
        $vin = isset($data['vin']) ? strtoupper(trim((string) $data['vin'])) : null;

        $vehicle = Vehicle::query()->create([
            'make' => trim($data['make']),
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'current_plate_display' => Plate::display($data['plate'] ?? null),
            'current_plate_normalized' => Plate::normalize($data['plate'] ?? null),
            'vin' => $vin !== '' ? $vin : null,
        ]);

        Audit::record('vehicle.created', $vehicle, actorId: $actor->id);

        return $vehicle;
    }

    /**
     * Possible duplicates for staff review only (same normalized plate or VIN). Never auto-merged.
     *
     * @return Collection<int, Vehicle>
     */
    public function possibleDuplicates(?string $plate, ?string $vin)
    {
        $normalized = Plate::normalize($plate);

        return Vehicle::query()
            ->where(function ($query) use ($normalized, $vin): void {
                if ($normalized) {
                    $query->orWhere('current_plate_normalized', $normalized);
                }

                if ($vin) {
                    $query->orWhere('vin', strtoupper($vin));
                }
            })
            ->when(! $normalized && ! $vin, fn ($query) => $query->whereRaw('1 = 0'))
            ->limit(5)
            ->get();
    }

    public function grantVehicleAccess(Customer $customer, Vehicle $vehicle, VehicleRelationship $relationship, User $actor, string $source = 'staff_verified'): VehicleCustomerAccess
    {
        $this->assertOffice($actor);

        $existing = VehicleCustomerAccess::query()
            ->where('customer_id', $customer->id)
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('ends_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $access = VehicleCustomerAccess::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'relationship' => $relationship,
            'starts_at' => now(),
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'source' => $source,
        ]);

        Audit::record('vehicle_access.granted', $access, ['customer' => $customer->id, 'vehicle' => $vehicle->id], actorId: $actor->id);

        return $access;
    }

    /**
     * Ownership change / revocation: the former relationship stops receiving reminders immediately.
     */
    public function endVehicleAccess(VehicleCustomerAccess $access, string $reason, User $actor): VehicleCustomerAccess
    {
        $this->assertOffice($actor);

        if ($access->ends_at !== null) {
            return $access;
        }

        $access->forceFill(['ends_at' => now()])->save();

        foreach ($access->vehicle->maintenancePlans()->where('customer_id', $access->customer_id)->get() as $plan) {
            $this->cancel->forPlan($plan, 'ownership_changed');
        }

        Audit::record('vehicle_access.ended', $access, reason: $reason, actorId: $actor->id);

        return $access;
    }

    /**
     * Link a portal account to a customer identity. The user must have verified the same phone via OTP.
     */
    public function linkPortalUser(Customer $customer, User $user, User $actor): Customer
    {
        $this->assertOffice($actor);

        if (! $user->isCustomer() || $user->phone_verified_at === null) {
            throw new DomainException('Зөвхөн утсаа баталгаажуулсан хэрэглэгчийн бүртгэлийг холбоно.');
        }

        if ($customer->user_id !== null && $customer->user_id !== $user->id) {
            throw new DomainException('Энэ үйлчлүүлэгч өөр бүртгэлтэй холбогдсон байна.');
        }

        if (Customer::query()->where('user_id', $user->id)->where('id', '!=', $customer->id)->exists()) {
            throw new DomainException('Энэ бүртгэл өөр үйлчлүүлэгчтэй холбогдсон байна. Нэгтгэх бол эзэн шийднэ.');
        }

        if ($user->phone_normalized !== $customer->normalized_contact_phone) {
            throw new DomainException('Бүртгэлийн баталгаажсан утас үйлчлүүлэгчийн утастай таарахгүй байна. Биечлэн шалгаад утсыг шинэчилнэ үү.');
        }

        $customer->forceFill(['user_id' => $user->id])->save();
        Audit::record('customer.portal_linked', $customer, ['user' => $user->id], actorId: $actor->id);

        return $customer;
    }

    private function assertOffice(User $actor): void
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Энэ үйлдлийг эзэн эсвэл зөвлөх хийнэ.');
        }
    }
}
