<?php

namespace App\Models;

use App\Enums\PreferredWindow;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class ServiceRequest extends Model
{
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => RequestType::class,
            'status' => RequestStatus::class,
            'preferred_window' => PreferredWindow::class,
            'preferred_local_date' => 'date',
            'contact_consent_at' => 'datetime',
            'reminder_preference' => 'boolean',
            'first_responded_at' => 'datetime',
            'converted_at' => 'datetime',
            'is_demo' => 'boolean',
            'vehicle_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
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
     * @return BelongsTo<Appointment, $this>
     */
    public function convertedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'converted_appointment_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function convertedWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'converted_work_order_id');
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }
}
