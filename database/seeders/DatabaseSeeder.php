<?php

namespace Database\Seeders;

use App\Enums\AppMode;
use App\Enums\UserRole;
use App\Models\Booth;
use App\Models\Shift;
use App\Models\TollRate;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Support\Toll;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * SAMPLE rates — replace with the bridge authority's official toll chart.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6?: bool}>
     */
    private const CATEGORIES = [
        ['MC', 'মোটরসাইকেল', 'Motorcycle', '🏍️', '#0ea5e9', 10],
        ['CNG', 'সিএনজি / অটোরিকশা', 'CNG / Auto-rickshaw', '🛺', '#22c55e', 20],
        ['CAR', 'প্রাইভেট কার / জিপ', 'Car / Jeep', '🚗', '#6366f1', 50],
        ['MICRO', 'মাইক্রোবাস', 'Microbus', '🚐', '#8b5cf6', 75],
        ['PICKUP', 'পিকআপ', 'Pickup', '🛻', '#f59e0b', 100],
        ['MINIBUS', 'মিনিবাস', 'Minibus', '🚌', '#f97316', 100],
        ['BUS', 'বড় বাস', 'Large bus', '🚍', '#ef4444', 150],
        ['TRUCK_S', 'ছোট ট্রাক', 'Small truck', '🚚', '#14b8a6', 150],
        ['TRUCK_L', 'বড় ট্রাক', 'Large truck', '🚛', '#be123c', 250],
        ['TRAILER', 'ট্রেইলার', 'Trailer', '🚜', '#78350f', 400],
        ['EXEMPT', 'ছাড়প্রাপ্ত (অ্যাম্বুলেন্স/ফায়ার)', 'Exempt (Ambulance/Fire)', '🚑', '#64748b', 0, true],
    ];

    public function run(): void
    {
        // A connected booth receives all master data (booths, users, categories...) from central.
        if (AppMode::isBooth() && Toll::syncEnabled()) {
            $this->command?->info('Connected booth: master data comes from the central server. Run: php artisan toll:sync');

            return;
        }

        $booth = Booth::query()->updateOrCreate(
            ['code' => config('toll.booth_code')],
            ['name' => 'বুথ-১', 'location' => 'সৈয়দ মাছ-উদ রুমী সেতু', 'printer_name' => 'POS58', 'is_active' => true],
        );

        $this->seedShifts();
        $this->seedCategories();

        // Demo accounts with the password "password" exist only on development machines.
        // On a real booth / server create the first admin with: php artisan toll:create-admin
        if (app()->environment('local', 'testing')) {
            $this->seedUsers($booth);
        }
    }

    private function seedShifts(): void
    {
        $shifts = [
            ['সকাল', '06:00:00', '14:00:00'],
            ['বিকাল', '14:00:00', '22:00:00'],
            ['রাত', '22:00:00', '06:00:00'],
        ];

        foreach ($shifts as $index => [$name, $start, $end]) {
            Shift::query()->updateOrCreate(['name' => $name], [
                'start_time' => $start,
                'end_time' => $end,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }

    private function seedCategories(): void
    {
        foreach (self::CATEGORIES as $index => $row) {
            [$code, $nameBn, $nameEn, $icon, $color, $amount] = $row;

            $category = VehicleCategory::query()->updateOrCreate(['code' => $code], [
                'name_bn' => $nameBn,
                'name_en' => $nameEn,
                'icon' => $icon,
                'color' => $color,
                'shortcut_key' => 'F'.($index + 1),
                'is_exempt' => $row[6] ?? false,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            TollRate::query()->firstOrCreate(
                ['vehicle_category_id' => $category->id],
                ['amount' => $amount, 'effective_from' => now()->subYear()->startOfDay(), 'note' => 'Sample rate'],
            );
        }
    }

    private function seedUsers(Booth $booth): void
    {
        $users = [
            ['Super Admin', '01700000001', UserRole::SuperAdmin, '1234'],
            ['Plaza Manager', '01700000002', UserRole::PlazaManager, '4321'],
            ['Booth Operator', '01700000003', UserRole::BoothOperator, null],
        ];

        foreach ($users as [$name, $phone, $role, $pin]) {
            User::query()->updateOrCreate(['phone' => $phone], [
                'name' => $name,
                'password' => 'password',
                'role' => $role,
                'booth_id' => $booth->id,
                'approval_pin' => $pin,
                'is_active' => true,
                'locale' => 'bn',
            ]);
        }
    }
}
