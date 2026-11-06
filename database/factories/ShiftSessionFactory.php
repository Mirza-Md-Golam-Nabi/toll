<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Booth;
use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftSession>
 */
class ShiftSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booth_id' => Booth::factory(),
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'status' => ShiftStatus::Open,
            'opened_at' => now(),
            'opening_cash' => 0,
        ];
    }
}
