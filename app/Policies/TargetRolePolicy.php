<?php

namespace App\Policies;

use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Models\TargetRole;
use App\Models\User;

class TargetRolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TargetRole $targetRole): bool
    {
        return $this->owns($user, $targetRole);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TargetRole $targetRole): bool
    {
        return $this->owns($user, $targetRole) && ! $targetRole->isArchived();
    }

    public function confirm(User $user, TargetRole $targetRole): bool
    {
        return $this->owns($user, $targetRole)
            && ! $targetRole->isArchived()
            && $targetRole->status !== TargetRoleStatus::AnalysisPending;
    }

    public function archive(User $user, TargetRole $targetRole): bool
    {
        return $this->owns($user, $targetRole) && $targetRole->isActive();
    }

    public function analyze(User $user, TargetRole $targetRole): bool
    {
        return $this->owns($user, $targetRole)
            && $targetRole->source_type === TargetRoleSourceType::JobListing
            && in_array($targetRole->status, [
                TargetRoleStatus::Draft,
                TargetRoleStatus::ReviewRequired,
                TargetRoleStatus::AnalysisFailed,
            ], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TargetRole $targetRole): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TargetRole $targetRole): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TargetRole $targetRole): bool
    {
        return false;
    }

    private function owns(User $user, TargetRole $targetRole): bool
    {
        return $user->id === $targetRole->user_id;
    }
}
