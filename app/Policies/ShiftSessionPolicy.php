<?php

namespace App\Policies;

use App\Enums\AppMode;
use App\Enums\Permission;
use App\Models\ShiftSession;
use App\Models\User;

class ShiftSessionPolicy
{
    /** Gates the menu, list and view pages; the Super Admin picks which roles get it. */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewShiftSessions);
    }

    /** Closing a shift from the collection screen only needs the operator to own the session. */
    public function view(User $user, ShiftSession $session): bool
    {
        return $user->hasPermission(Permission::ViewAllRecords) || $session->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ShiftSession $session): bool
    {
        return false;
    }

    public function delete(User $user, ShiftSession $session): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function close(User $user, ShiftSession $session): bool
    {
        return AppMode::isBooth() && $session->isOpen() && $this->view($user, $session);
    }
}
