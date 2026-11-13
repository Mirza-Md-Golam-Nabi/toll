<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Services\RolePermissionService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Permissions are shown grouped (`permissions.<group>`) and saved through the service,
 * which also writes the change to the audit trail.
 */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Permissions').': '.$this->getRecord()->userRole()->getLabel();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $granted = app(RolePermissionService::class)->permissionsOf($this->getRecord()->userRole());

        $data['permissions'] = collect($granted)
            ->groupBy(fn (Permission $permission): string => $permission->group())
            ->map(fn ($permissions): array => $permissions->map(fn (Permission $permission): string => $permission->value)->values()->all())
            ->all();

        return $data;
    }

    /**
     * @param  Role  $record
     * @param  array{permissions?: array<string, list<string>>}  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(RolePermissionService::class)->update($record->userRole(), Arr::flatten($data['permissions'] ?? []));

        return $record;
    }
}
