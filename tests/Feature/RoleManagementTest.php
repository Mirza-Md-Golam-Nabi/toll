<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports;
use App\Filament\Pages\TollCollection;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\TollStatsOverview;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\RolePermissionService;
use App\Services\Sync\SyncIngestService;
use Illuminate\Support\Str;
use Livewire\Livewire;

function roleRecord(UserRole $role): Role
{
    return Role::findByName($role->value);
}

it('installs the three roles with the defaults the app had before', function () {
    $service = app(RolePermissionService::class);

    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(['booth_operator', 'plaza_manager', 'super_admin'])
        ->and($service->permissionsOf(UserRole::SuperAdmin))->toBe(Permission::cases())
        ->and($service->permissionsOf(UserRole::BoothOperator))->toBe([Permission::CollectToll, Permission::ViewShiftSummary, Permission::ViewTollTransactions, Permission::ViewShiftSessions])
        ->and(UserRole::PlazaManager->hasPermission(Permission::ViewReports))->toBeTrue()
        ->and(UserRole::PlazaManager->hasPermission(Permission::ManageUsers))->toBeFalse();
});

it('lets the super admin change a role and audits the change', function () {
    $admin = User::factory()->superAdmin()->create();
    $manager = User::factory()->manager()->create();

    $this->actingAs($admin)->get(RoleResource::getUrl())->assertOk();

    Livewire::test(EditRole::class, ['record' => roleRecord(UserRole::PlazaManager)->getRouteKey()])
        ->assertFormFieldExists('permissions.reports')
        ->fillForm(['permissions' => array_merge(
            array_map(fn (): array => [], Permission::grouped()),
            ['users' => [Permission::ViewUsers->value], 'toll' => [Permission::CollectToll->value]],
        )])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(RolePermissionService::class)->permissionsOf(UserRole::PlazaManager))->toBe([Permission::CollectToll, Permission::ViewUsers])
        ->and(AuditLog::query()->where('event', AuditEvent::PermissionsChanged)->count())->toBe(1);

    $this->actingAs($manager)->get(Reports::getUrl())->assertForbidden();
    $this->actingAs($manager)->get(UserResource::getUrl())->assertOk();
});

it('keeps role management to the super admin and the super admin role untouchable', function () {
    $this->actingAs(User::factory()->manager()->create())->get(RoleResource::getUrl())->assertForbidden();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(RoleResource::getUrl('edit', ['record' => roleRecord(UserRole::SuperAdmin)]))
        ->assertForbidden();

    expect(fn () => app(RolePermissionService::class)->update(UserRole::SuperAdmin, []))->toThrow(InvalidArgumentException::class);
});

it('stops a user manager from touching super admin accounts', function () {
    app(RolePermissionService::class)->update(UserRole::PlazaManager, [Permission::ViewUsers, Permission::ManageUsers]);

    $manager = User::factory()->manager()->create();
    $admin = User::factory()->superAdmin()->create();
    $operator = User::factory()->operator()->create();

    expect($manager->can('update', $operator))->toBeTrue()
        ->and($manager->can('update', $admin))->toBeFalse()
        ->and($manager->can('delete', $admin))->toBeFalse();
});

it('makes override approval follow the permission', function () {
    $operator = User::factory()->operator()->create();
    $approvals = app(ApprovalService::class);

    expect($approvals->requiresPin($operator))->toBeTrue();

    app(RolePermissionService::class)->update(UserRole::BoothOperator, [Permission::CollectToll, Permission::ApproveOverrides]);

    expect($approvals->requiresPin($operator->fresh()))->toBeFalse();
});

it('refuses phone sign in to roles that may not collect toll', function () {
    config(['toll.mode' => 'central']);
    $setup = tollSetup();
    app(RolePermissionService::class)->update(UserRole::BoothOperator, []);

    $this->postJson('/api/mobile/login', [
        'phone' => $setup->operator->phone,
        'password' => User::DEFAULT_PIN,
        'device_uuid' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonPath('message', __('This account is not allowed to collect toll.'));
});

it('ships role permissions to booths and applies them there', function () {
    $service = app(RolePermissionService::class);
    $service->update(UserRole::PlazaManager, [Permission::ViewReports]);

    $exported = app(SyncIngestService::class)->masterData()['role_permissions'];

    expect($exported)->toBe([
        'plaza_manager' => [Permission::ViewReports->value],
        'booth_operator' => [
            Permission::CollectToll->value,
            Permission::ViewShiftSummary->value,
            Permission::ViewTollTransactions->value,
            Permission::ViewShiftSessions->value,
        ],
    ]);

    $service->import(['plaza_manager' => [Permission::ViewShifts->value, 'unknown.permission'], 'super_admin' => []]);

    expect($service->permissionsOf(UserRole::PlazaManager))->toBe([Permission::ViewShifts])
        ->and($service->permissionsOf(UserRole::SuperAdmin))->toBe(Permission::cases());
});

it('lets the super admin choose which roles see the dashboard collection summary', function () {
    $operator = User::factory()->operator()->create();
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager);
    expect(TollStatsOverview::canView())->toBeTrue();

    $this->actingAs($operator);
    expect(TollStatsOverview::canView())->toBeFalse();
    $this->get(Dashboard::getUrl())->assertRedirect(TollCollection::getUrl());

    app(RolePermissionService::class)->update(UserRole::BoothOperator, [Permission::CollectToll, Permission::ViewCollectionSummary]);
    app(RolePermissionService::class)->update(UserRole::PlazaManager, [Permission::ViewDashboardStats]);

    $this->actingAs($operator->fresh());
    expect(TollStatsOverview::canView())->toBeTrue()
        ->and(RevenueChart::canView())->toBeFalse();
    $this->get(Dashboard::getUrl())->assertOk();

    $this->actingAs($manager->fresh());
    expect(TollStatsOverview::canView())->toBeFalse()
        ->and(RevenueChart::canView())->toBeTrue();
});

it('lets the super admin choose which roles see the shift summary on the collection screen', function () {
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator);

    Livewire::test(TollCollection::class)
        ->assertSet('canViewShiftSummary', true)
        ->assertSee(__('Collected this shift'));

    app(RolePermissionService::class)->update(UserRole::BoothOperator, [Permission::CollectToll]);

    $this->actingAs($operator->fresh());

    Livewire::test(TollCollection::class)
        ->assertSet('canViewShiftSummary', false)
        ->assertDontSee(__('Collected this shift'))
        ->assertSee(__('Select vehicle category'));
});
