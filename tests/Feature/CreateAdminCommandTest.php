<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VehicleCategory;
use Database\Seeders\DatabaseSeeder;

it('creates a super admin with an approval PIN', function () {
    $this->artisan('toll:create-admin', [
        '--name' => 'Owner',
        '--phone' => '+8801912345678',
        '--password' => 'secret-pass',
        '--pin' => '2468',
    ])->assertSuccessful();

    $user = User::query()->where('phone', '01912345678')->sole();

    expect($user->role)->toBe(UserRole::SuperAdmin)
        ->and($user->checkApprovalPin('2468'))->toBeTrue();
});

it('rejects weak input', function () {
    $this->artisan('toll:create-admin', [
        '--name' => 'Owner',
        '--phone' => '12345',
        '--password' => 'short',
        '--pin' => '12',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('seeds demo accounts only outside production', function () {
    $this->seed();
    expect(User::query()->where('phone', '01700000001')->exists())->toBeTrue();

    User::query()->delete();
    app()->detectEnvironment(fn () => 'production');
    app(DatabaseSeeder::class)->run();

    expect(User::query()->count())->toBe(0);
});

it('leaves master data to central on a connected booth', function () {
    config(['toll.mode' => 'booth', 'toll.sync.central_url' => 'http://central.test', 'toll.sync.token' => 'x']);

    app(DatabaseSeeder::class)->run();

    expect(VehicleCategory::query()->count())->toBe(0);
});
