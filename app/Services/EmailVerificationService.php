<?php

namespace App\Services;

use App\Exceptions\TollException;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Verifies a user's email with a 6-digit code sent to that address.
 * Only the hash of the code is kept (in the cache), bound to the email it was sent to.
 */
class EmailVerificationService
{
    public const EXPIRES_IN_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_AFTER_SECONDS = 60;

    public function sendCode(User $user): void
    {
        if (blank($user->email)) {
            throw TollException::because('Add an email address first.');
        }

        if ($user->hasVerifiedEmail()) {
            throw TollException::because('This email address is already verified.');
        }

        $throttleKey = 'email-verification-send:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 1)) {
            throw TollException::because('Please wait :seconds seconds before requesting a new code.', [
                'seconds' => RateLimiter::availableIn($throttleKey),
            ]);
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user), [
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::EXPIRES_IN_MINUTES));

        try {
            $user->notify(new EmailVerificationCode($code, self::EXPIRES_IN_MINUTES));
        } catch (Throwable $e) {
            report($e);
            Cache::forget($this->cacheKey($user));

            throw TollException::because('The email could not be sent. Please check the mail settings.');
        }

        RateLimiter::hit($throttleKey, self::RESEND_AFTER_SECONDS);
    }

    public function verify(User $user, string $code): void
    {
        /** @var array{email: string, code_hash: string, attempts: int}|null $pending */
        $pending = Cache::get($this->cacheKey($user));

        if ($pending === null || $pending['email'] !== $user->email || $pending['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->cacheKey($user));

            throw TollException::because('The code has expired. Please request a new one.');
        }

        if (! Hash::check(trim($code), $pending['code_hash'])) {
            Cache::put($this->cacheKey($user), [...$pending, 'attempts' => $pending['attempts'] + 1], now()->addMinutes(self::EXPIRES_IN_MINUTES));

            throw TollException::because('The verification code is incorrect.');
        }

        Cache::forget($this->cacheKey($user));
        $user->markEmailAsVerified();
    }

    private function cacheKey(User $user): string
    {
        return 'email-verification:'.$user->id;
    }
}
