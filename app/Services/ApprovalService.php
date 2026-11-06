<?php

namespace App\Services;

use App\Exceptions\TollException;
use App\Models\User;

/**
 * Resolves who approves a manual override. Managers/admins approve their own actions;
 * an operator needs a manager's PIN.
 */
class ApprovalService
{
    public function requiresPin(User $actor): bool
    {
        return ! $actor->role->canApproveOverrides();
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
