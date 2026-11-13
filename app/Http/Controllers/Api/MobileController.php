<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\MobileLoginRequest;
use App\Http\Requests\MobilePushRequest;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileDeviceService;
use App\Services\Mobile\MobileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * API of the offline Android collection app (central server only).
 */
class MobileController extends Controller
{
    public function __construct(
        private MobileDeviceService $devices,
        private MobileSyncService $sync,
    ) {}

    /**
     * Reachability probe of the app: it only trusts the connection once the central server answers.
     */
    public function ping(): JsonResponse
    {
        abort_unless(AppMode::isCentral(), Response::HTTP_NOT_FOUND);

        return response()->json(['ok' => true, 'server_time' => now()->toDateTimeString()]);
    }

    public function login(MobileLoginRequest $request): JsonResponse
    {
        abort_unless(AppMode::isCentral(), Response::HTTP_NOT_FOUND);

        $credentials = $request->credentials();
        $user = User::query()->where('phone', $credentials['phone'])->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages(['phone' => __('filament-panels::auth/pages/login.messages.failed')]);
        }

        ['device' => $device, 'token' => $token] = $this->devices->signIn($user, $request->validated('device_uuid'), $request->validated('device_name'));

        return response()->json(['token' => $token, 'data' => $this->sync->bootstrap($device)]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->sync->bootstrap($this->device($request))]);
    }

    public function push(MobilePushRequest $request): JsonResponse
    {
        return response()->json($this->sync->ingest(
            $this->device($request),
            $request->validated('sessions'),
            $request->validated('tickets'),
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->devices->signOut($this->device($request));

        return response()->json(['ok' => true]);
    }

    private function device(Request $request): MobileDevice
    {
        return $request->attributes->get('mobileDevice');
    }
}
