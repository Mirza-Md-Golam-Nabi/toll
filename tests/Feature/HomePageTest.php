<?php

use App\Models\VehicleCategory;

it('shows the toll chart in Bangla with Bangla digits', function () {
    VehicleCategory::factory()->withRate(300)->create(['name_bn' => 'ট্রেইলার', 'sort_order' => 1]);
    VehicleCategory::factory()->withRate(40)->create(['name_bn' => 'সিডান কার', 'sort_order' => 2]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['ট্রেইলার', '৩০০.০০', 'সিডান কার', '৪০.০০'])
        ->assertSee('Powered by');
});

it('shows exempt categories as free and hides inactive ones', function () {
    VehicleCategory::factory()->exempt()->withRate(0)->create(['name_bn' => 'অ্যাম্বুলেন্স']);
    VehicleCategory::factory()->withRate(90)->create(['name_bn' => 'পুরনো শ্রেণী', 'is_active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('অ্যাম্বুলেন্স')
        ->assertSee('ফ্রি')
        ->assertDontSee('পুরনো শ্রেণী');
});

it('shows English names and Latin digits when the locale is English', function () {
    VehicleCategory::factory()->withRate(150)->create(['name_bn' => 'মিডিয়াম ট্রাক', 'name_en' => 'Medium truck']);

    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Medium truck')
        ->assertSee('150.00');
});

it('renders when no toll rates exist yet', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('টোলের হার এখনো প্রকাশ করা হয়নি।');
});
