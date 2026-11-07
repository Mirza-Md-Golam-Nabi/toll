<?php

namespace App\Filament\Auth;

use App\Filament\Support\Fields;
use App\Support\PhoneNumber;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Panel login with the 11-digit Bangladeshi mobile number instead of an email address.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return Fields::mobileNumber()
            ->autocomplete('tel')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{phone: ?string, password: string}
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'phone' => PhoneNumber::normalize($data['phone']),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
