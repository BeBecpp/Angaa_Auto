<?php

namespace Database\Factories;

use App\Models\ShopBay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopBay>
 */
class ShopBayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Пост '.fake()->unique()->numberBetween(1, 99),
            'active' => true,
        ];
    }
}
