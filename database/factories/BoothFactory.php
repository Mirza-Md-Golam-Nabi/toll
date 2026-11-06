<?php

namespace Database\Factories;

use App\Models\Booth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booth>
 */
class BoothFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'B'.fake()->unique()->numerify('##'),
            'name' => 'Booth '.fake()->numberBetween(1, 9),
            'location' => fake()->city(),
            'is_active' => true,
        ];
    }
}
