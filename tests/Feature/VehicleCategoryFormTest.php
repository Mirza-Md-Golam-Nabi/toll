<?php

use App\Filament\Resources\VehicleCategories\Pages\CreateVehicleCategory;
use App\Filament\Resources\VehicleCategories\Pages\EditVehicleCategory;
use App\Models\User;
use App\Models\VehicleCategory;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

it('creates a category with only the Bangla name and a rate', function () {
    Livewire::test(CreateVehicleCategory::class)
        ->fillForm(['code' => '', 'name_bn' => 'সিডান কার', 'name_en' => '', 'initial_rate' => 40])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = VehicleCategory::query()->where('name_bn', 'সিডান কার')->firstOrFail();

    expect($category->code)->toBeNull()
        ->and($category->name_en)->toBeNull()
        ->and($category->english_name)->toBe('সিডান কার')
        ->and((float) $category->rates()->value('amount'))->toBe(40.0);
});

it('requires the Bangla name', function () {
    Livewire::test(CreateVehicleCategory::class)
        ->fillForm(['name_bn' => '', 'initial_rate' => 40])
        ->call('create')
        ->assertHasFormErrors(['name_bn' => 'required']);
});

it('stores the code upper-case and rejects a duplicate regardless of case or spacing', function () {
    VehicleCategory::factory()->create(['code' => 'car']);

    expect(VehicleCategory::query()->value('code'))->toBe('CAR');

    Livewire::test(CreateVehicleCategory::class)
        ->fillForm(['code' => ' Car ', 'name_bn' => 'প্রাইভেট কার', 'initial_rate' => 40])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
});

it('allows several categories without a code', function () {
    VehicleCategory::factory()->create(['code' => null]);

    Livewire::test(CreateVehicleCategory::class)
        ->fillForm(['code' => null, 'name_bn' => 'কৃষি যান', 'initial_rate' => 90])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(VehicleCategory::query()->whereNull('code')->count())->toBe(2);
});

it('keeps its own code when editing', function () {
    $category = VehicleCategory::factory()->create(['code' => 'BUS']);

    Livewire::test(EditVehicleCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['code' => 'bus', 'name_en' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->refresh()->name_en)->toBeNull();
});
