<?php

namespace Database\Seeders;

use App\Models\Booth;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoothSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The booth this installation runs as (TOLL_BOOTH_CODE).
     */
    public function run(): void
    {
        Booth::query()->updateOrCreate(
            ['code' => config('toll.booth_code')],
            ['name' => 'বুথ-১', 'location' => 'সৈয়দ মাছ-উদ রুমী সেতু', 'printer_name' => 'POS58', 'is_active' => true],
        );
    }
}
