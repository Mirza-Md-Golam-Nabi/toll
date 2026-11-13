<?php

namespace App\Http\Controllers;

use App\Models\VehicleCategory;
use App\Services\RateService;
use App\Support\Settings;
use Illuminate\Contracts\View\View;

/**
 * Public landing page that shows the toll chart currently in force.
 */
class HomeController extends Controller
{
    /** Class letters printed on the official chart (ক → ঞ). */
    private const BANGLA_CLASS_LETTERS = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ'];

    public function __invoke(RateService $rates): View
    {
        $isBangla = app()->getLocale() === 'bn';
        $classIndex = 0;

        $categories = $rates->activeCategories()->map(function (VehicleCategory $category) use ($isBangla, &$classIndex): array {
            $classLetter = null;

            if (! $category->is_exempt) {
                $classLetter = $isBangla
                    ? (self::BANGLA_CLASS_LETTERS[$classIndex] ?? $this->localizeDigits((string) ($classIndex + 1), $isBangla))
                    : chr(65 + ($classIndex % 26));
                $classIndex++;
            }

            return [
                'name' => $category->display_name,
                'secondaryName' => $isBangla ? $category->name_en : $category->name_bn,
                'icon' => $category->icon ?: '🚗',
                'color' => $category->color ?: '#14b8a6',
                'isExempt' => $category->is_exempt,
                'classLetter' => $classLetter,
                'amount' => (float) $category->current_amount,
                'formattedAmount' => $this->localizeDigits(number_format((float) $category->current_amount, 2), $isBangla),
            ];
        });

        $highestAmount = max(1, (float) $categories->max('amount'));

        return view('home', [
            'bridgeName' => Settings::bridgeName(),
            'currency' => Settings::get('currency', '৳'),
            'helpline' => Settings::get('helpline'),
            'categories' => $categories->map(fn (array $category): array => [
                ...$category,
                'share' => (int) round($category['amount'] / $highestAmount * 100),
            ]),
            'categoryCount' => $this->localizeDigits((string) $categories->where('isExempt', false)->count(), $isBangla),
            'lowestAmount' => $this->localizeDigits(number_format((float) $categories->where('isExempt', false)->min('amount')), $isBangla),
            'highestAmount' => $this->localizeDigits(number_format($highestAmount), $isBangla),
            'isBangla' => $isBangla,
        ]);
    }

    private function localizeDigits(string $value, bool $isBangla): string
    {
        return $isBangla ? strtr($value, ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯']) : $value;
    }
}
