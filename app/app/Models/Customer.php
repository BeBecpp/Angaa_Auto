<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Customer extends Model
{
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<VehicleCustomerAccess, $this>
     */
    public function vehicleAccesses(): HasMany
    {
        return $this->hasMany(VehicleCustomerAccess::class);
    }

    /**
     * @return HasMany<CommunicationConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(CommunicationConsent::class);
    }

    /**
     * @return HasMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /**
     * Vehicles this customer can currently see (explicit, unexpired access only).
     *
     * @return BelongsToMany<Vehicle, $this>
     */
    public function currentVehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_customer_access')
            ->wherePivotNull('ends_at')
            ->withPivot(['relationship', 'starts_at', 'verified_at']);
    }

    public function firstName(): string
    {
        return (string) str($this->display_name)->explode(' ')->first();
    }
}
