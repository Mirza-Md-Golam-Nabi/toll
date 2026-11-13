<?php

use App\Filament\Auth\EditProfile;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
});

function sentCode(User $user): string
{
    $code = null;
    Notification::assertSentTo($user, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('opens the profile page from the user menu', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSee(__('My profile'));
});

it('adds an email from the profile as unverified', function () {
    $user = User::factory()->manager()->create();
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'Manager@Example.com', 'currentPassword' => User::DEFAULT_PIN])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->email)->toBe('manager@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->canReceiveReports())->toBeFalse();
});

it('verifies the email with the emailed code', function () {
    $user = User::factory()->manager()->create(['email' => 'manager@example.com']);
    $this->actingAs($user);

    $page = Livewire::test(EditProfile::class)->mountFormComponentAction('email', 'verifyEmail');

    $page->setFormComponentActionData(['code' => sentCode($user)])
        ->callMountedFormComponentAction()
        ->assertHasNoFormComponentActionErrors();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->canReceiveReports())->toBeTrue()
        ->and(User::query()->reportRecipients()->pluck('id')->all())->toBe([$user->id]);
});

it('rejects a wrong code', function () {
    $user = User::factory()->manager()->create(['email' => 'manager@example.com']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->mountFormComponentAction('email', 'verifyEmail')
        ->setFormComponentActionData(['code' => '000000'])
        ->callMountedFormComponentAction();

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('resets verification when the email changes', function () {
    $user = User::factory()->manager()->withEmail()->create();

    $user->update(['email' => 'new@example.com']);

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse()
        ->and(User::query()->reportRecipients()->exists())->toBeFalse();
});

it('only sends reports to verified admins and managers', function () {
    User::factory()->manager()->withEmail(verified: false)->create();
    User::factory()->manager()->create(['email' => null]);
    User::factory()->operator()->withEmail()->create();
    $verified = User::factory()->superAdmin()->withEmail()->create();

    expect(User::query()->reportRecipients()->pluck('id')->all())->toBe([$verified->id]);
});
