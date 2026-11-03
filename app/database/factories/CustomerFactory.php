<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+976'.fake()->unique()->numerify('9#######');

        return [
            'display_name' => fake()->firstName().' '.fake()->lastName(),
            'contact_phone' => Phone::display($phone),
            'normalized_contact_phone' => $phone,
        ];
    }
}
