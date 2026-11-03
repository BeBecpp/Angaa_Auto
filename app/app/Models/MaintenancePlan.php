<?php

namespace App\Models;

use App\Enums\PlanStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class MaintenancePlan extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'due_date' => 'date',
            'due_km' => 'integer',
            'is_demo' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function sourceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'source_work_order_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<OdometerReading, $this>
     */
    public function basedOnReading(): BelongsTo
    {
        return $this->belongsTo(OdometerReading::class, 'based_on_reading_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<MaintenancePlan, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'supersedes_id');
    }
}
