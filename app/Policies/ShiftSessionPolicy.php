<?php

namespace App\Policies;

use App\Enums\AppMode;
use App\Enums\UserRole;
use App\Models\ShiftSession;
use App\Models\User;

class ShiftSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ShiftSession $session): bool
    {
        return $user->isAtLeast(UserRole::PlazaManager) || $session->user_id === $user->id;
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
