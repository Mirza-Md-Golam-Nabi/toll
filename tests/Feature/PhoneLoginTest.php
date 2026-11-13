<?php

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('normalises Bangladeshi mobile numbers', function (string $input, ?string $expected, bool $valid) {
    expect(PhoneNumber::normalize($input))->toBe($expected)
        ->and(PhoneNumber::isValid($input))->toBe($valid);
})->with([
    'plain' => ['01712345678', '01712345678', true],
    'country code' => ['+880 1712-345678', '01712345678', true],
    'bangla digits' => ['০১৭১২৩৪৫৬৭৮', '01712345678', true],
    'too short' => ['0171234567', '0171234567', false],
    'invalid operator' => ['01212345678', '01212345678', false],
    'email' => ['admin@toll.test', null, false],
]);

it('logs in with the mobile number', function () {
    $user = User::factory()->create(['phone' => '01712345678']);

    Livewire::test(Login::class)
        ->fillForm(['phone' => '+8801712345678', 'password' => User::DEFAULT_PIN])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong PIN or an invalid number', function (string $phone, string $pin) {
    User::factory()->create(['phone' => '01712345678']);

    Livewire::test(Login::class)
        ->fillForm(['phone' => $phone, 'password' => $pin])
        ->call('authenticate')
        ->assertHasFormErrors(['phone']);

    $this->assertGuest();
})->with([
    'wrong PIN' => ['01712345678', '9999'],
    'invalid number' => ['12345', User::DEFAULT_PIN],
]);

it('lets an admin register a user by mobile number', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'New Operator',
            'phone' => '০১৮১২৩৪৫৬৭৮',
            'role' => 'booth_operator',
            'locale' => 'bn',
            'password' => '56789',
        ])
        ->assertHasNoFormErrors();

    expect(User::query()->where('phone', '01812345678')->exists())->toBeTrue();
});

it('refuses a duplicate mobile number', function () {
    User::factory()->create(['phone' => '01812345678']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Duplicate',
            'phone' => '+8801812345678',
            'role' => 'booth_operator',
            'locale' => 'bn',
            'password' => '56789',
        ])
        ->assertHasFormErrors(['phone' => 'unique']);
});

it('rejects a PIN that is not 4-5 digits', function (string $pin) {
    User::factory()->create(['phone' => '01712345678']);

    Livewire::test(Login::class)
        ->fillForm(['phone' => '01712345678', 'password' => $pin])
        ->call('authenticate')
        ->assertHasFormErrors(['password']);

    $this->assertGuest();
})->with(['too short' => '123', 'too long' => '123456', 'letters' => 'abcd']);

it('fills the visible default PIN when an admin creates a user', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->mountAction('create')
        ->assertSchemaStateSet(['password' => User::DEFAULT_PIN])
        ->assertFormFieldExists('password', fn (TextInput $field): bool => ! $field->isPassword())
        ->fillForm(['name' => 'New Operator', 'phone' => '01812345678', 'role' => 'booth_operator', 'locale' => 'bn'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $user = User::query()->where('phone', '01812345678')->sole();

    expect(Hash::check(User::DEFAULT_PIN, $user->password))->toBeTrue();
});

it('keeps the current PIN when the field is left blank on edit', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $operator = User::factory()->create();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($operator), ['name' => 'Renamed', 'password' => ''])
        ->assertHasNoFormErrors();

    expect(Hash::check(User::DEFAULT_PIN, $operator->fresh()->password))->toBeTrue();
});

it('lets a user change their own PIN from the profile', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm(['password' => '24680', 'passwordConfirmation' => '24680', 'currentPassword' => User::DEFAULT_PIN])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('24680', $user->fresh()->password))->toBeTrue();
});
