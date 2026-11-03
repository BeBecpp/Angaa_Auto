<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class WorkOrder extends Model
{
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'status_before_wait' => WorkOrderStatus::class,
            'customer_snapshot' => 'array',
            'vehicle_snapshot' => 'array',
            'received_at' => 'datetime',
            'diagnosed_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'promised_ready_at' => 'datetime',
            'owner_override_at' => 'datetime',
            'is_demo' => 'boolean',
            'intake_km' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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
     * @return BelongsTo<User, $this>
     */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_mechanic_id');
    }

    /**
     * @return BelongsTo<ShopBay, $this>
     */
    public function bay(): BelongsTo
    {
        return $this->belongsTo(ShopBay::class, 'bay_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<EstimateRevision, $this>
     */
    public function currentApprovedRevision(): BelongsTo
    {
        return $this->belongsTo(EstimateRevision::class, 'current_approved_estimate_revision_id');
    }

    /**
     * @return HasMany<WorkOrderStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(WorkOrderStatusEvent::class);
    }

    /**
     * @return HasMany<InspectionItem, $this>
     */
    public function inspectionItems(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }

    /**
     * @return HasMany<EstimateRevision, $this>
     */
    public function estimateRevisions(): HasMany
    {
        return $this->hasMany(EstimateRevision::class);
    }

    /**
     * @return HasMany<RepairItem, $this>
     */
    public function repairItems(): HasMany
    {
        return $this->hasMany(RepairItem::class);
    }

    /**
     * @return HasMany<QcCheck, $this>
     */
    public function qcChecks(): HasMany
    {
        return $this->hasMany(QcCheck::class);
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<ResourceReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(ResourceReservation::class);
    }

    /**
     * @return HasMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class, 'source_work_order_id');
    }

    public function latestRevision(): ?EstimateRevision
    {
        return $this->estimateRevisions()->orderByDesc('revision_number')->first();
    }

    public function currentInvoice(): ?Invoice
    {
        return $this->invoices()->where('status', '!=', 'void')->latest('id')->first();
    }
}
