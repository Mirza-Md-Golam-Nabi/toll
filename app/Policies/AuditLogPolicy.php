<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The audit trail is read-only for everyone.
 */
class AuditLogPolicy extends MasterDataPolicy
{
    protected function canManage(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }
}
