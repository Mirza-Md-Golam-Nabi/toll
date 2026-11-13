<?php

namespace Database\Factories;

use App\Models\TollRate;
use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleCategory>
 */
class VehicleCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'code' => strtoupper(substr($name, 0, 6)).fake()->unique()->numberBetween(1, 999),
            'name_bn' => $name,
            'name_en' => ucfirst($name),
            'color' => fake()->hexColor(),
            'icon' => '🚗',
            'is_exempt' => false,
            'is_active' => true,
        ];
    }

    public function exempt(): static
    {
        return $this->state(['is_exempt' => true]);
    }

    /** Category with a rate already in force. */
    public function withRate(float $amount): static
    {
        return $this->afterCreating(fn (VehicleCategory $category) => TollRate::query()->create([
            'vehicle_category_id' => $category->id,
            'amount' => $amount,
            'effective_from' => now()->subDay(),
        ]));
    }
}
