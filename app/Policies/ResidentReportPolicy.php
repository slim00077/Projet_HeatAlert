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

    /**
     * Following up an incident (status and documented resolution) is the residence manager's job.
     */
    public function followUp(User $user, ResidentReport $report): bool
    {
        return $user->isManager() && $report->residence->user_id === $user->id;
    }

    /**
     * AI insights (similar incidents, previous resolutions) for a report: its author or its manager.
     */
    public function viewInsights(User $user, ResidentReport $report): bool
    {
        return $this->view($user, $report) || $this->followUp($user, $report);
    }
}
