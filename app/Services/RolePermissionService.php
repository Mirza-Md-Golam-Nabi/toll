<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * The single place that reads and changes which permissions each role holds.
 * Roles are the fixed `UserRole` cases; the Super Admin implicitly holds every permission.
 */
class RolePermissionService
{
    public function __construct(
        private PermissionRegistrar $registrar,
        private AuditLogger $audit,
    ) {}

    /**
     * Creates any missing role/permission rows. A newly created permission is granted to its
     * default roles; permissions that already exist keep whatever the Super Admin chose.
     */
    public function install(): void
    {
        DB::transaction(function (): void {
            foreach (UserRole::cases() as $role) {
                Role::findOrCreate($role->value, $this->guard());
            }

            foreach (Permission::cases() as $permission) {
                $model = PermissionModel::query()->firstOrCreate(['name' => $permission->value, 'guard_name' => $this->guard()]);

                if ($model->wasRecentlyCreated) {
                    $roles = [UserRole::SuperAdmin, ...$permission->defaultRoles()];
                    $model->roles()->syncWithoutDetaching(Role::query()->whereIn('name', array_map(fn (UserRole $role): string => $role->value, $roles))->pluck('id'));
                }
            }
        });

        $this->registrar->forgetCachedPermissions();
    }

    public function roleHas(UserRole $role, Permission $permission): bool
    {
        if ($role === UserRole::SuperAdmin) {
            return true;
        }

        return (bool) $this->registrar
            ->getPermissions(['name' => $permission->value, 'guard_name' => $this->guard()], true)
            ->first()
            ?->roles
            ->contains('name', $role->value);
    }

    /** @return list<UserRole> */
    public function rolesWith(Permission $permission): array
    {
        return array_values(array_filter(UserRole::cases(), fn (UserRole $role): bool => $this->roleHas($role, $permission)));
    }

    /** @return list<Permission> */
    public function permissionsOf(UserRole $role): array
    {
        return array_values(array_filter(Permission::cases(), fn (Permission $permission): bool => $this->roleHas($role, $permission)));
    }

    /**
     * Replaces a role's permissions and records the change in the audit trail.
     *
     * @param  list<Permission|string>  $permissions
     */
    public function update(UserRole $role, array $permissions): void
    {
        if ($role === UserRole::SuperAdmin) {
            throw new InvalidArgumentException('The Super Admin always holds every permission.');
        }

        $old = array_map(fn (Permission $permission): string => $permission->value, $this->permissionsOf($role));
        $new = collect($permissions)
            ->map(fn (Permission|string $permission): string => Permission::from($permission instanceof Permission ? $permission->value : $permission)->value)
            ->unique()
            ->sort()
            ->values()
            ->all();

        sort($old);

        if ($old === $new) {
            return;
        }

        $model = Role::findByName($role->value, $this->guard());
        $model->syncPermissions($new);

        $this->audit->log(AuditEvent::PermissionsChanged, $model, ['permissions' => $old], ['permissions' => $new]);
    }

    /** @return array<string, list<string>> role => permission names, as served to booths. */
    public function export(): array
    {
        return collect(UserRole::cases())
            ->reject(fn (UserRole $role): bool => $role === UserRole::SuperAdmin)
            ->mapWithKeys(fn (UserRole $role): array => [
                $role->value => array_map(fn (Permission $permission): string => $permission->value, $this->permissionsOf($role)),
            ])
            ->all();
    }

    /**
     * Applies the central server's role permissions on a booth (unknown names are ignored).
     *
     * @param  array<string, list<string>>  $rolePermissions
     */
    public function import(array $rolePermissions): void
    {
        if ($rolePermissions === []) {
            return;
        }

        foreach ($rolePermissions as $roleName => $names) {
            $role = UserRole::tryFrom($roleName);

            if ($role === null || $role === UserRole::SuperAdmin) {
                continue;
            }

            $wanted = collect($names)->filter(fn (string $name): bool => Permission::tryFrom($name) !== null)->sort()->values()->all();
            $current = collect($this->permissionsOf($role))->map(fn (Permission $permission): string => $permission->value)->sort()->values()->all();

            if ($wanted !== $current) {
                Role::findByName($role->value, $this->guard())->syncPermissions($wanted);
            }
        }
    }

    private function guard(): string
    {
        return config('auth.defaults.guard');
    }
}
