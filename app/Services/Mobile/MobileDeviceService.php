<?php

namespace App\Services\Mobile;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Exceptions\TollException;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers Android phones and issues / revokes their access tokens.
 * A phone keeps one token for one signed-in operator until logout or until an admin blocks it.
 */
class MobileDeviceService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Signs the operator in on a phone. The phone is registered on first use and gets a
     * short code (M1, M2...) that is unique within the operator's booth.
     *
     * @return array{device: MobileDevice, token: string}
     */
    public function signIn(User $user, string $deviceUuid, ?string $deviceName = null): array
    {
        if (! $user->is_active) {
            throw TollException::because('This account is inactive.');
        }

        if (! $user->hasPermission(Permission::CollectToll)) {
            throw TollException::because('This account is not allowed to collect toll.');
        }

        if ($user->booth_id === null) {
            throw TollException::because('No booth is assigned to this user.');
        }

        $plainToken = Str::random(64);

        $device = DB::transaction(function () use ($user, $deviceUuid, $deviceName, $plainToken): MobileDevice {
            $device = MobileDevice::query()->where('device_uuid', $deviceUuid)->lockForUpdate()->first();

            if ($device && ! $device->is_active) {
                throw TollException::because('This phone has been blocked. Contact the plaza manager.');
            }

            $device ??= new MobileDevice(['device_uuid' => $deviceUuid]);

            if ($device->booth_id !== $user->booth_id) {
                $device->booth_id = $user->booth_id;
                $device->code = $this->nextCode($user->booth_id);
            }

            $device->fill([
                'name' => $deviceName ?: $device->name,
                'user_id' => $user->id,
                'token_hash' => MobileDevice::hashToken($plainToken),
                'last_seen_at' => now(),
            ])->save();

            return $device;
        });

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->log(AuditEvent::Login, $device, new: ['device' => $device->code], actor: $user);

        return ['device' => $device->load('booth', 'user'), 'token' => $plainToken];
    }

    /** The active phone (with its active operator) that owns this token, or null. */
    public function resolve(?string $plainToken): ?MobileDevice
    {
        if (blank($plainToken)) {
            return null;
        }

        $device = MobileDevice::query()
            ->active()
            ->where('token_hash', MobileDevice::hashToken($plainToken))
            ->with(['booth', 'user'])
            ->first();

        if (! $device?->user?->is_active
            || $device->user->booth_id !== $device->booth_id
            || ! $device->user->hasPermission(Permission::CollectToll)) {
            return null;
        }

        return $device;
    }

    public function signOut(MobileDevice $device): void
    {
        $this->audit->log(AuditEvent::Logout, $device, actor: $device->user);

        $device->update(['token_hash' => null, 'user_id' => null]);
    }

    /** Blocks the phone: its token stops working immediately. */
    public function block(MobileDevice $device): void
    {
        $device->update(['is_active' => false, 'token_hash' => null]);
    }

    private function nextCode(int $boothId): string
    {
        $highest = MobileDevice::query()
            ->where('booth_id', $boothId)
            ->pluck('code')
            ->map(fn (string $code): int => (int) substr($code, 1))
            ->max();

        return 'M'.(($highest ?? 0) + 1);
    }
}
