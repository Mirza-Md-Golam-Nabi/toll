<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A row for each `UserRole` case; its permissions are chosen by the Super Admin.
 */
class Role extends SpatieRole
{
    /** The enum case this row stands for (the row name is the enum value). */
    public function userRole(): UserRole
    {
        return UserRole::from($this->name);
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === UserRole::SuperAdmin->value;
    }

    /** Users holding this role (`users.role` stores the role name). */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'name');
    }
}
