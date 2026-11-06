<?php

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Automatically writes created / updated / deleted entries to the audit trail.
 * Models may define `protected array $auditExclude = [...]` to hide columns.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit(AuditEvent::Created, [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function (Model $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAudit(AuditEvent::Updated, $original, $changes);
        });

        static::deleted(fn (Model $model) => $model->writeAudit(AuditEvent::Deleted, $model->auditableAttributes($model->getAttributes()), []));
    }

    protected function writeAudit(AuditEvent $event, array $old, array $new): void
    {
        app(AuditLogger::class)->log($event, $this, $old, $new);
    }

    protected function auditableAttributes(array $attributes): array
    {
        $hidden = array_merge(
            ['created_at', 'updated_at', 'synced_at', 'remember_token', 'password', 'approval_pin'],
            $this->auditExclude ?? [],
        );

        return array_diff_key($attributes, array_flip($hidden));
    }
}
