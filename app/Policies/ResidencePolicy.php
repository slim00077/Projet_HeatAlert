<?php

namespace App\Policies;

use App\Models\Residence;
use App\Models\User;

class ResidencePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Only residence managers (and admins) create residences; managers' submissions need approval.
     */
    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, Residence $residence): bool
    {
        if ($residence->user_id === $user->id) {
            return true;
        }

        return $residence->isApproved() && $residence->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Residence $residence): bool
    {
        return $residence->user_id === $user->id;
    }

    /**
     * Managers cannot delete a residence once it was approved.
     */
    public function delete(User $user, Residence $residence): bool
    {
        if ($residence->user_id !== $user->id) {
            return false;
        }

        return ! $user->isManager() || ! $residence->isApproved();
    }

    /**
     * Adding and removing residents is the manager's job, and only on approved residences.
     */
    public function manageMembers(User $user, Residence $residence): bool
    {
        return $user->isManager() && $residence->user_id === $user->id && $residence->isApproved();
    }
}
