<?php

use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\VehicleRegistry;

it('adds repeat passes to the stored pass count', function () {
    $category = VehicleCategory::factory()->create();
    $pass = ['plate_number' => 'DHAKA METRO-GA 11-1111', 'vehicle_category_id' => $category->id];
    $registry = app(VehicleRegistry::class);

    $registry->recordPasses([$pass + ['issued_at' => now()->subHours(2)], $pass + ['issued_at' => now()->subHour()]]);
    $registry->recordPasses([$pass + ['issued_at' => now()]]);

    expect(Vehicle::query()->where('plate_number', $pass['plate_number'])->value('pass_count'))->toBe(3);
});
