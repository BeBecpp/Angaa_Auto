<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(2, true)),
            'name_mn' => 'Туршилтын үйлчилгээ '.fake()->unique()->numberBetween(1, 9999),
            'price_type' => PriceType::Fixed,
            'price_mnt' => 30000,
            'estimated_duration_min' => 60,
            'active' => true,
            'published' => true,
        ];
    }
}
