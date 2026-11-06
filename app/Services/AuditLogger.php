<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Booth;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The single writer for the audit trail (operator log + manual overrides).
 */
class AuditLogger
{
    public function log(
        AuditEvent $event,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $reason = null,
        ?User $approver = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= auth()->user();

        return AuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'booth_id' => $actor?->booth_id ?? Booth::current()?->getKey(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'reason' => $reason,
            'approved_by' => $approver?->getKey(),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }
}
