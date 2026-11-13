<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Day',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_active' => true,
        ];
    }
}
