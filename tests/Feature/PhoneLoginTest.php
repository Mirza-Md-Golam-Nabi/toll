<?php

use App\Filament\Auth\Login;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\PhoneNumber;
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
        ->fillForm(['phone' => '+8801712345678', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password or an invalid number', function (string $phone, string $password) {
    User::factory()->create(['phone' => '01712345678']);

    Livewire::test(Login::class)
        ->fillForm(['phone' => $phone, 'password' => $password])
        ->call('authenticate')
        ->assertHasFormErrors(['phone']);

    $this->assertGuest();
})->with([
    'wrong password' => ['01712345678', 'wrong-password'],
    'invalid number' => ['12345', 'password'],
]);

it('lets an admin register a user by mobile number', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'New Operator',
            'phone' => '০১৮১২৩৪৫৬৭৮',
            'role' => 'booth_operator',
            'locale' => 'bn',
            'password' => 'secret-pass',
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
            'password' => 'secret-pass',
        ])
        ->assertHasFormErrors(['phone' => 'unique']);
});
