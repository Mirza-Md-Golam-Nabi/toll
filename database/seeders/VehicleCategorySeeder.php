<?php

namespace Database\Seeders;

use App\Models\TollRate;
use App\Models\VehicleCategory;
use App\Services\RateService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Official toll chart of Syed Masud Rumi Bridge (Kushtia Roads Division). Loaded and empty
     * vehicles pay the same rate. Order follows the chart (ক → ঞ); exempt vehicles come last.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6?: bool}>
     */
    private const CATEGORIES = [
        ['TRAILER', 'ট্রেইলার', 'Trailer', '🚛', '#78350f', 300],
        ['TRUCK_L', 'হেভি ট্রাক', 'Heavy truck', '🚚', '#be123c', 180],
        ['TRUCK_M', 'মিডিয়াম ট্রাক', 'Medium truck', '🚚', '#ea580c', 150],
        ['BUS', 'বড় বাস', 'Large bus', '🚍', '#ef4444', 120],
        ['TRUCK_S', 'মিনি ট্রাক', 'Mini truck', '🛻', '#14b8a6', 115],
        ['AGRI', 'কৃষি কাজে ব্যবহৃত যান', 'Agricultural vehicle', '🚜', '#65a30d', 90],
        ['MINIBUS', 'মিনি বাস / কোস্টার', 'Mini bus / Coaster', '🚌', '#f97316', 60],
        ['MICRO', 'মাইক্রোবাস', 'Microbus', '🚐', '#8b5cf6', 60],
        ['4WD', 'ফোর হুইল চালিত যানবাহন', 'Four-wheel drive vehicle', '🚙', '#0ea5e9', 60],
        ['CAR', 'সিডান কার', 'Sedan car', '🚗', '#6366f1', 40],
        ['EXEMPT', 'ছাড়প্রাপ্ত (অ্যাম্বুলেন্স/ফায়ার)', 'Exempt (Ambulance/Fire)', '🚑', '#64748b', 0, true],
    ];

    /** Sample categories from earlier releases that are not on the official chart. */
    private const RETIRED_CODES = ['MC', 'CNG', 'PICKUP'];

    /**
     * Vehicle categories of the official chart, each with its current toll rate.
     */
    public function run(): void
    {
        $this->retireOldCategories();

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

            $this->seedRate($category, $amount);
        }

        RateService::flush();
    }

    /** Shortcut keys are unique and get reassigned, so release them first; retired categories are deactivated. */
    private function retireOldCategories(): void
    {
        VehicleCategory::query()
            ->whereIn('code', [...array_column(self::CATEGORIES, 0), ...self::RETIRED_CODES])
            ->update(['shortcut_key' => null]);

        VehicleCategory::query()->whereIn('code', self::RETIRED_CODES)->update(['is_active' => false]);
    }

    /** First rate is back-dated; a changed chart rate is added as a new rate effective now (history is kept). */
    private function seedRate(VehicleCategory $category, int $amount): void
    {
        $current = $category->currentRate()->first();

        if ($current && (float) $current->amount === (float) $amount) {
            return;
        }

        TollRate::query()->create([
            'vehicle_category_id' => $category->id,
            'amount' => $amount,
            'effective_from' => $current ? now() : now()->subYear()->startOfDay(),
            'note' => 'Official toll chart',
        ]);
    }
}
