<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalises number plates so manual entry and ANPR output match the same vehicle.
 * Stored format: "DHAKA METRO-GA 12-3456".
 */
class PlateNumber
{
    public static function normalize(?string $plate): ?string
    {
        if ($plate === null) {
            return null;
        }

        $plate = Str::of($plate)
            ->upper()
            ->replaceMatches('/[^A-Z0-9\-\s\x{0980}-\x{09FF}]/u', '')
            ->replaceMatches('/\s*-\s*/', '-')
            ->squish()
            ->toString();

        return $plate === '' ? null : $plate;
    }

    /** Common Bangladeshi registration series used by the plate picker. */
    public static function series(): array
    {
        return ['KA', 'KHA', 'GA', 'GHA', 'CHA', 'THA', 'DA', 'BA', 'MA', 'LA', 'HA', 'TA', 'DHA', 'NA', 'PA', 'BHA', 'SHA', 'JA', 'ZA', 'U', 'E', 'TO', 'THO', 'DO'];
    }
}
