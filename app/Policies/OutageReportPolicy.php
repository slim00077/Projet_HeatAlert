<?php

namespace App\Policies;

use App\Models\OutageReport;
use App\Models\User;

class OutageReportPolicy
{
    public function view(User $user, OutageReport $outageReport): bool
    {
        return $outageReport->user_id === $user->id;
    }

    public function update(User $user, OutageReport $outageReport): bool
    {
        return $outageReport->user_id === $user->id;
    }

    public function delete(User $user, OutageReport $outageReport): bool
    {
        return $outageReport->user_id === $user->id;
    }
}
