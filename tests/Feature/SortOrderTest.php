<?php

use App\Filament\Resources\VehicleCategories\Pages\CreateVehicleCategory;
use App\Filament\Resources\VehicleCategories\Pages\EditVehicleCategory;
use App\Models\Shift;
use App\Models\User;
use App\Models\VehicleCategory;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Names of the shifts in sort order, keyed by position.
 *
 * @return array<int, string>
 */
function shiftSequence(): array
{
    return Shift::query()->orderBy('sort_order')->pluck('name', 'sort_order')->all();
}

function makeShifts(string ...$names): void
{
    foreach ($names as $name) {
        Shift::factory()->create(['name' => $name]);
    }
}

it('appends a record without a position to the end', function () {
    makeShifts('A', 'B', 'C');

    expect(shiftSequence())->toBe([1 => 'A', 2 => 'B', 3 => 'C'])
        ->and(Shift::nextSortOrder())->toBe(4);
});

it('inserts at a position and moves the rest down by one', function () {
    makeShifts('A', 'B', 'C');

    Shift::factory()->create(['name' => 'New', 'sort_order' => 2]);

    expect(shiftSequence())->toBe([1 => 'A', 2 => 'New', 3 => 'B', 4 => 'C']);
});

it('moves a record up and shifts only the rows in between', function () {
    makeShifts('A', 'B', 'C', 'D', 'E');

    Shift::query()->where('name', 'D')->firstOrFail()->update(['sort_order' => 2]);

    expect(shiftSequence())->toBe([1 => 'A', 2 => 'D', 3 => 'B', 4 => 'C', 5 => 'E']);
});

it('moves a record down and shifts only the rows in between', function () {
    makeShifts('A', 'B', 'C', 'D', 'E');

    Shift::query()->where('name', 'B')->firstOrFail()->update(['sort_order' => 4]);

    expect(shiftSequence())->toBe([1 => 'A', 2 => 'C', 3 => 'D', 4 => 'B', 5 => 'E']);
});

it('closes the gap when a record is deleted', function () {
    makeShifts('A', 'B', 'C');

    Shift::query()->where('name', 'A')->firstOrFail()->delete();

    expect(shiftSequence())->toBe([1 => 'B', 2 => 'C']);
});

it('reorders the neighbours with a single query', function () {
    makeShifts('A', 'B', 'C', 'D');
    $shift = Shift::query()->where('name', 'D')->firstOrFail();

    DB::enableQueryLog();
    $shift->update(['sort_order' => 1]);
    $updates = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_starts_with($sql, 'update'));

    // One bulk update for the neighbours, one for the record itself.
    expect($updates)->toHaveCount(2)
        ->and(shiftSequence())->toBe([1 => 'D', 2 => 'A', 3 => 'B', 4 => 'C']);
});

describe('vehicle category form', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->superAdmin()->create());
        VehicleCategory::factory()->count(2)->create();
    });

    it('suggests the next position on create', function () {
        Livewire::test(CreateVehicleCategory::class)
            ->assertSchemaStateSet(['sort_order' => 3]);
    });

    it('inserts a new category at the chosen position', function () {
        Livewire::test(CreateVehicleCategory::class)
            ->fillForm(['name_bn' => 'নতুন', 'initial_rate' => 40, 'sort_order' => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(VehicleCategory::query()->orderBy('sort_order')->pluck('sort_order', 'name_bn')->keys()->first())->toBe('নতুন')
            ->and(VehicleCategory::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2, 3]);
    });

    it('moves a category when its position is edited', function () {
        $last = VehicleCategory::factory()->create();

        Livewire::test(EditVehicleCategory::class, ['record' => $last->getRouteKey()])
            ->fillForm(['sort_order' => 1])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(VehicleCategory::query()->orderBy('sort_order')->value('id'))->toBe($last->id)
            ->and(VehicleCategory::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2, 3]);
    });
});
