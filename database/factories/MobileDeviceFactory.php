<?php

namespace Database\Factories;

use App\Models\Booth;
use App\Models\MobileDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MobileDevice>
 */
class MobileDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_uuid' => (string) Str::uuid(),
            'booth_id' => Booth::factory(),
            'code' => 'M'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Android phone',
            'is_active' => true,
        ];
    }

    public function blocked(): static
    {
        return $this->state(['is_active' => false, 'token_hash' => null]);
    }
}
