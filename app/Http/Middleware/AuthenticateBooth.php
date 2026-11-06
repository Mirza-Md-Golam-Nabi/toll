<?php

namespace App\Http\Middleware;

use App\Enums\AppMode;
use App\Models\Booth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the central sync API: shared bearer token + a known, active booth code.
 * The resolved booth is attached to the request for the controller.
 */
class AuthenticateBooth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('toll.sync.token');

        abort_unless(AppMode::isCentral(), Response::HTTP_NOT_FOUND);
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), Response::HTTP_UNAUTHORIZED);

        $booth = Booth::query()->active()->where('code', $request->input('booth_code'))->first();
        abort_unless($booth, Response::HTTP_FORBIDDEN, 'Unknown booth.');

        $request->attributes->set('booth', $booth);

        return $next($request);
    }
}
