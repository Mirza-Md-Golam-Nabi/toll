<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Booth;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::BoothOperator,
            'is_active' => true,
            'locale' => 'bn',
        ];
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

    public function superAdmin(): static
    {
        return $this->state(['role' => UserRole::SuperAdmin, 'approval_pin' => '1234']);
    }

    public function manager(string $pin = '1234'): static
    {
        return $this->state(['role' => UserRole::PlazaManager, 'approval_pin' => $pin]);
    }

    public function operator(?Booth $booth = null): static
    {
        return $this->state(fn () => [
            'role' => UserRole::BoothOperator,
            'booth_id' => $booth?->id ?? Booth::factory(),
        ]);
    }
}
