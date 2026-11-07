<?php

namespace App\Support;

/**
 * Bangladeshi mobile numbers — the login identity of every user.
 * Stored format: 11 digits, e.g. "01712345678".
 */
class PhoneNumber
{
    /** 01 + operator digit (3-9) + 8 digits. */
    public const PATTERN = '/^01[3-9]\d{8}$/';

    private const BANGLA_DIGITS = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    /** Accepts Bangla digits, spaces/dashes and the +880 / 880 country prefix. */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', strtr($phone, self::BANGLA_DIGITS));

        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }

        return $digits === '' ? null : $digits;
    }

    public static function isValid(?string $phone): bool
    {
        $phone = self::normalize($phone);

        return $phone !== null && preg_match(self::PATTERN, $phone) === 1;
    }
}
