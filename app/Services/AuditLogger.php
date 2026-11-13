<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Booth;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * Writes many entries with ONE insert, keeping each entry's own time
     * (used for batches synced from phones, which happened earlier offline).
     *
     * @param  list<array{event: AuditEvent, subject: Model, new: array<string, mixed>, actor: User, at: CarbonInterface}>  $entries
     */
    public function logMany(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $ip = app()->runningInConsole() ? null : request()->ip();

        $rows = array_map(fn (array $entry): array => [
            'id' => (string) Str::uuid7(),
            'user_id' => $entry['actor']->getKey(),
            'booth_id' => $entry['actor']->booth_id,
            'event' => $entry['event']->value,
            'auditable_type' => $entry['subject']->getMorphClass(),
            'auditable_id' => $entry['subject']->getKey(),
            'old_values' => null,
            'new_values' => $entry['new'] ? json_encode($entry['new'], JSON_UNESCAPED_UNICODE) : null,
            'reason' => null,
            'approved_by' => null,
            'ip_address' => $ip,
            'created_at' => $entry['at'],
        ], $entries);

        foreach (array_chunk($rows, 500) as $chunk) {
            AuditLog::query()->insert($chunk);
        }
    }
}
