<?php

namespace App\Enums;

enum AppMode: string
{
    /** Runs at the toll booth with its own local database; pushes data to central. */
    case Booth = 'booth';

    /** Online server that receives data from every booth and serves reports remotely. */
    case Central = 'central';

    public static function current(): self
    {
        return self::tryFrom((string) config('toll.mode')) ?? self::Booth;
    }

    public static function isBooth(): bool
    {
        return self::current() === self::Booth;
    }

    public static function isCentral(): bool
    {
        return self::current() === self::Central;
    }
}
