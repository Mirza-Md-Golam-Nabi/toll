<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Booth;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('changes a booth user\'s PIN from the edit modal', function () {
    $admin = User::factory()->superAdmin()->create();
    $operator = User::factory()->operator()->create(['booth_id' => Booth::factory()->create()->id]);

    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($operator), ['password' => '5678'])
        ->assertHasNoFormErrors();

    expect(Hash::check('5678', $operator->fresh()->password))->toBeTrue();
});
