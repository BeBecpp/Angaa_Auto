<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plate = fake()->unique()->numerify('####').' ТСТ';

        return [
            'make' => fake()->randomElement(['Toyota', 'Hyundai', 'Nissan', 'Honda']),
            'model' => fake()->randomElement(['Prius', 'Accent', 'X-Trail', 'Fit']),
            'year' => fake()->numberBetween(2005, 2022),
            'current_plate_display' => $plate,
            'current_plate_normalized' => str_replace(' ', '', $plate),
        ];
    }
}
