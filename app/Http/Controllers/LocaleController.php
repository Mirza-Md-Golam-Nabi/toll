<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->saveQuietly();

        return redirect()->back();
    }
}
