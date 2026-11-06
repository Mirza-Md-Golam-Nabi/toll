<?php

namespace App\Policies;

use App\Enums\AppMode;
use App\Enums\UserRole;
use App\Models\TollTransaction;
use App\Models\User;

/**
 * Tickets are never created/edited/deleted through CRUD — only via the collection
 * screen and audited overrides, which happen at the booth (the source of truth).
 */
class TollTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TollTransaction $transaction): bool
    {
        return $user->isAtLeast(UserRole::PlazaManager) || $transaction->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TollTransaction $transaction): bool
    {
        return false;
    }

    public function delete(User $user, TollTransaction $transaction): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Void / category correction / reprint. Approval is enforced separately. */
    public function override(User $user, TollTransaction $transaction): bool
    {
        return AppMode::isBooth()
            && ! $transaction->isVoided()
            && $this->view($user, $transaction);
    }
}
