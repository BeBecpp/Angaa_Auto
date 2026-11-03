<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Type and staff role are never mass assignable; set them with forceFill in trusted code paths.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'staff_role' => StaffRole::class,
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && $this->active && $this->password !== null;
    }

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff && $this->staff_role !== null;
    }

    public function isCustomer(): bool
    {
        return $this->type === UserType::Customer;
    }

    public function isOwner(): bool
    {
        return $this->isStaff() && $this->staff_role === StaffRole::Owner;
    }

    public function isAdvisor(): bool
    {
        return $this->isStaff() && $this->staff_role === StaffRole::Advisor;
    }

    public function isMechanic(): bool
    {
        return $this->isStaff() && $this->staff_role === StaffRole::Mechanic;
    }

    /**
     * Owner or service advisor: front-office permissions.
     */
    public function isOfficeStaff(): bool
    {
        return $this->isOwner() || $this->isAdvisor();
    }

    public function hasRole(StaffRole ...$roles): bool
    {
        return $this->isStaff() && $this->active && in_array($this->staff_role, $roles, true);
    }

    /**
     * @return HasOne<StaffProfile, $this>
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /**
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }
}
