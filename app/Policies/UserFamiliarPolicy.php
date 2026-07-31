<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserFamiliar;

class UserFamiliarPolicy
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
    public function view(User $user, UserFamiliar $userFamiliar): bool
    {
        return $user->id === $userFamiliar->user_id;
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
    public function update(User $user, UserFamiliar $userFamiliar): bool
    {
        return $user->id === $userFamiliar->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserFamiliar $userFamiliar): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserFamiliar $userFamiliar): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserFamiliar $userFamiliar): bool
    {
        return false;
    }
}
