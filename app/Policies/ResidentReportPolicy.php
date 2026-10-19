<?php

namespace App\Policies;

use App\Models\ResidentReport;
use App\Models\User;

class ResidentReportPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, ResidentReport $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function update(User $user, ResidentReport $report): bool
    {
        return $report->user_id === $user->id;
    }

    public function delete(User $user, ResidentReport $report): bool
    {
        return $report->user_id === $user->id;
    }
}
