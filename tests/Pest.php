<?php

use App\Models\Booth;
use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A ready-to-collect booth: booth, all-day shift, operator, manager (PIN 1234) and a car category at ৳50.
 *
 * @return object{booth: Booth, shift: Shift, operator: User, manager: User, car: VehicleCategory}
 */
function tollSetup(): object
{
    $booth = Booth::factory()->create(['code' => config('toll.booth_code')]);

    return (object) [
        'booth' => $booth,
        'shift' => Shift::factory()->create(),
        'operator' => User::factory()->operator($booth)->create(),
        'manager' => User::factory()->manager('1234')->create(['booth_id' => $booth->id]),
        'car' => VehicleCategory::factory()->withRate(50)->create(['code' => 'CAR', 'shortcut_key' => 'F3']),
    ];
}

/** Opens a shift for the operator through the real service. */
function openShift(object $setup, float $openingCash = 0): ShiftSession
{
    return app(ShiftService::class)->open($setup->operator, $setup->shift, $openingCash);
}
