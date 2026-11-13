<?php

namespace App\Services;

use App\Enums\Permission;
use App\Exceptions\TollException;
use App\Models\User;

/**
 * Resolves who approves a manual override. Users whose role may approve overrides approve
 * their own actions; everyone else needs an approver's PIN.
 */
class ApprovalService
{
    public function requiresPin(User $actor): bool
    {
        return ! $actor->hasPermission(Permission::ApproveOverrides);
    }

    public function resolve(User $actor, ?int $approverId = null, ?string $pin = null): User
    {
        if (! $this->requiresPin($actor)) {
            return $actor;
        }

        $approver = $approverId ? User::query()->approvers()->find($approverId) : null;

        if (! $approver || blank($pin) || ! $approver->checkApprovalPin($pin)) {
            throw TollException::because('Invalid manager PIN.');
        }

        return $approver;
    }

    /** @return array<int, string> id => name of users who can approve. */
    public function approverOptions(): array
    {
        return User::query()->approvers()->orderBy('name')->pluck('name', 'id')->all();
    }
}
