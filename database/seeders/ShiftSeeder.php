<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The three 8-hour shifts of the day.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const SHIFTS = [
        ['সকাল', '06:00:00', '14:00:00'],
        ['বিকাল', '14:00:00', '22:00:00'],
        ['রাত', '22:00:00', '06:00:00'],
    ];

    public function run(): void
    {
        foreach (self::SHIFTS as $index => [$name, $start, $end]) {
            Shift::query()->updateOrCreate(['name' => $name], [
                'start_time' => $start,
                'end_time' => $end,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }
}
