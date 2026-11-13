<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveScope;
use App\Services\RolePermissionService;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['name', 'phone', 'email', 'password', 'role', 'booth_id', 'approval_pin', 'is_active', 'locale', 'last_login_at'])]
#[Hidden(['password', 'remember_token', 'approval_pin'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasActiveScope, HasFactory, Notifiable;

    /** Login PIN given to new accounts; stored hashed in the `password` column. */
    public const DEFAULT_PIN = '1234';

    protected array $auditExclude = ['last_login_at'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'approval_pin' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /** A changed email address must be verified again before it receives reports. */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('email') && ! $user->isDirty('email_verified_at')) {
                $user->email_verified_at = null;
            }
        });
    }

    /** The login identity: always stored as 11 digits (01XXXXXXXXX). */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => PhoneNumber::normalize($value));
    }

    /** Optional; used only to send reports. */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => filled($value) ? Str::lower(trim($value)) : null);
    }

    public function canReceiveReports(): bool
    {
        return $this->is_active
            && $this->hasPermission(Permission::ViewReports)
            && filled($this->email)
            && $this->hasVerifiedEmail();
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    public function shiftSessions(): HasMany
    {
        return $this->hasMany(ShiftSession::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->role->hasPermission($permission);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function checkApprovalPin(string $pin): bool
    {
        return $this->approval_pin !== null && Hash::check($pin, $this->approval_pin);
    }

    /** Users whose role holds the given permission. */
    public function scopeWithPermission(Builder $query, Permission $permission): Builder
    {
        $roles = app(RolePermissionService::class)->rolesWith($permission);

        return $query->whereIn('role', array_map(fn (UserRole $role): string => $role->value, $roles));
    }

    /** Users allowed to approve manual overrides. */
    public function scopeApprovers(Builder $query): Builder
    {
        return $query->active()
            ->withPermission(Permission::ApproveOverrides)
            ->whereNotNull('approval_pin');
    }

    /** Users who get emailed reports — never an unverified address. */
    public function scopeReportRecipients(Builder $query): Builder
    {
        return $query->active()
            ->withPermission(Permission::ViewReports)
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at');
    }
}
