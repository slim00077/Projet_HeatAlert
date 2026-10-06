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

    public function view(User $user, Residence $residence): bool
    {
        return $residence->user_id === $user->id;
    }

    public function update(User $user, Residence $residence): bool
    {
        return $residence->user_id === $user->id;
    }

    public function delete(User $user, Residence $residence): bool
    {
        return $residence->user_id === $user->id;
    }
}
