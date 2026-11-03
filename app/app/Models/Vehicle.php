<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Vehicle extends Model
{
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_demo' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<VehicleCustomerAccess, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(VehicleCustomerAccess::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<OdometerReading, $this>
     */
    public function odometerReadings(): HasMany
    {
        return $this->hasMany(OdometerReading::class);
    }

    /**
     * @return HasMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function label(): string
    {
        return trim(implode(' ', array_filter([$this->current_plate_display, $this->make, $this->model, $this->year])));
    }

    /**
     * Latest verified reading that has not itself been corrected by a later entry.
     */
    public function latestVerifiedReading(): ?OdometerReading
    {
        return $this->odometerReadings()
            ->where('verification_state', 'verified')
            ->whereNotIn('id', OdometerReading::query()->whereNotNull('correction_of_id')->select('correction_of_id'))
            ->latest('observed_at')
            ->latest('id')
            ->first();
    }
}
