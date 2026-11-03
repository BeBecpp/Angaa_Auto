<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Default: an active owner staff account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => UserType::Staff,
            'staff_role' => StaffRole::Owner,
            'active' => true,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(StaffRole $role, bool $bookable = false): static
    {
        return $this->state(fn (): array => ['type' => UserType::Staff, 'staff_role' => $role])
            ->afterCreating(fn (User $user) => $user->staffProfile()->create(['display_name' => $user->name, 'bookable' => $bookable]));
    }

    public function owner(): static
    {
        return $this->role(StaffRole::Owner);
    }

    public function advisor(): static
    {
        return $this->role(StaffRole::Advisor);
    }

    public function mechanic(): static
    {
        return $this->role(StaffRole::Mechanic, bookable: true);
    }

    public function customer(?string $phone = null): static
    {
        return $this->state(fn (): array => [
            'type' => UserType::Customer,
            'staff_role' => null,
            'email' => null,
            'password' => null,
            'phone_normalized' => $phone ?? '+976'.fake()->unique()->numerify('9#######'),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
