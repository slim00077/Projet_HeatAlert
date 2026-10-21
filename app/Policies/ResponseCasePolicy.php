<?php

namespace App\Policies;

use App\Enums\ResponseStage;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\User;

class ResponseCasePolicy
{
    /**
     * Admins manage every case, but only the reporting resident can confirm a resolution.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() && $ability !== 'confirm' ? true : null;
    }

    /**
     * Opening a response case is the job of the manager who owns the (approved) residence.
     */
    public function create(User $user, Residence $residence): bool
    {
        return $user->isManager() && $residence->user_id === $user->id && $residence->isApproved();
    }

    /**
     * The response room (tasks, internal notes, every linked report) is for the residence's manager only.
     */
    public function view(User $user, ResponseCase $responseCase): bool
    {
        return $user->isManager() && $responseCase->residence->user_id === $user->id;
    }

    public function update(User $user, ResponseCase $responseCase): bool
    {
        return $this->view($user, $responseCase);
    }

    /**
     * A resident confirms (or disputes) the resolution of their own report once the work is awaiting verification.
     */
    public function confirm(User $user, ResponseCase $responseCase, ResidentReport $report): bool
    {
        return $user->isResident()
            && $report->user_id === $user->id
            && in_array($responseCase->stage, [ResponseStage::Verification, ResponseStage::Resolved], true)
            && $responseCase->reports()->whereKey($report->id)->exists();
    }
}
