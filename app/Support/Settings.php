<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Single access point for editable application settings (cached, merged with config defaults).
 */
class Settings
{
    private const CACHE_KEY = 'toll.settings';

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => array_merge(
            config('toll.defaults'),
            array_filter(Setting::query()->pluck('value', 'key')->all(), fn ($value) => $value !== null),
        ));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function bridgeName(): string
    {
        return self::get(app()->getLocale() === 'bn' ? 'bridge_name_bn' : 'bridge_name_en');
    }
}
