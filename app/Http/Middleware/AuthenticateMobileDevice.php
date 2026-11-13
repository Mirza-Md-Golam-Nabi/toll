<?php

namespace App\Http\Middleware;

use App\Enums\AppMode;
use App\Services\Mobile\MobileDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the Android app API: the phone's own bearer token must belong to an active
 * phone whose signed-in operator is still active and still assigned to the phone's booth.
 * The resolved device is attached to the request for the controller.
 */
class AuthenticateMobileDevice
{
    public function __construct(private MobileDeviceService $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AppMode::isCentral(), Response::HTTP_NOT_FOUND);

        $device = $this->devices->resolve($request->bearerToken());
        abort_unless($device, Response::HTTP_UNAUTHORIZED, __('Your session has ended. Please sign in again.'));

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->attributes->set('mobileDevice', $device);

        return $next($request);
    }
}
