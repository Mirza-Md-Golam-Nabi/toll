<?php

namespace App\Filament\Auth;

use App\Filament\Support\Feedback;
use App\Filament\Support\Fields;
use App\Models\User;
use App\Services\EmailVerificationService;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The signed-in user's own profile. The mobile number (login) is managed by an admin;
 * the optional email receives reports once it has been verified with an emailed code.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            Fields::mobileNumber()
                ->disabled()
                ->dehydrated(false)
                ->helperText(__('Your login number. Ask an admin to change it.')),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return Fields::loginPin('New PIN')
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->dehydrated(fn (?string $state): bool => filled($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordConfirmationFormComponent();

        return $field->label(__('Confirm new PIN'))->validationAttribute(__('Confirm new PIN'))->inputMode('numeric');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getCurrentPasswordFormComponent();

        return $field->label(__('Current PIN'))
            ->validationAttribute(__('Current PIN'))
            ->belowContent(__('Enter your current PIN to save these changes.'))
            ->inputMode('numeric');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->nullable()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->live(debounce: 500)
            ->helperText(fn (): string => $this->emailStatusText())
            ->suffixAction($this->verifyEmailAction());
    }

    public function verifyEmailAction(): Action
    {
        return Action::make('verifyEmail')
            ->label(__('Verify email'))
            ->tooltip(__('Verify email'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('warning')
            ->visible(fn (Get $get): bool => $this->needsVerification() && $get('email') === $this->getUser()->email)
            ->modalHeading(__('Verify email'))
            ->modalDescription(fn (): string => __('A 6-digit code has been sent to :email. Did not get it? Close this window and try again after a minute.', [
                'email' => $this->getUser()->email,
            ]))
            ->mountUsing(function (?Schema $schema): void {
                $schema?->fill();
                Feedback::attempt(fn () => app(EmailVerificationService::class)->sendCode($this->getUser()));
            })
            ->schema([
                TextInput::make('code')
                    ->label(__('Verification code'))
                    ->required()
                    ->length(6)
                    ->autocomplete('one-time-code'),
            ])
            ->modalSubmitActionLabel(__('Verify'))
            ->action(fn (array $data, Action $action) => Feedback::attempt(
                fn () => app(EmailVerificationService::class)->verify($this->getUser(), $data['code']),
                __('Email verified. Reports will be sent to this address.'),
                $action,
            ));
    }

    public function getUser(): User
    {
        /** @var User */
        return parent::getUser();
    }

    private function needsVerification(): bool
    {
        return filled($this->getUser()->email) && ! $this->getUser()->hasVerifiedEmail();
    }

    private function emailStatusText(): string
    {
        $user = $this->getUser();

        return match (true) {
            blank($user->email) => __('Optional. Add an email address to receive reports.'),
            $user->hasVerifiedEmail() => __('Verified — reports will be sent to this address.'),
            default => __('Not verified — no reports are sent until you verify it.'),
        };
    }
}
