<?php

namespace Database\Seeders;

use App\Enums\AppMode;
use App\Support\Toll;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's master data.
     */
    public function run(): void
    {
        if ($this->receivesMasterDataFromCentral()) {
            $this->command?->info('Connected booth: master data comes from the central server. Run: php artisan toll:sync');

            return;
        }

        $this->call([
            BoothSeeder::class,
            ShiftSeeder::class,
            VehicleCategorySeeder::class,
        ]);

        if ($this->isDevelopmentMachine()) {
            $this->call(UserSeeder::class);
        }
    }

    /** A connected booth receives all master data (booths, users, categories...) from central. */
    private function receivesMasterDataFromCentral(): bool
    {
        return AppMode::isBooth() && Toll::syncEnabled();
    }

    /** Demo accounts (login PIN User::DEFAULT_PIN) must never exist on a real booth / server. */
    private function isDevelopmentMachine(): bool
    {
        return app()->environment('local', 'testing');
    }
}
