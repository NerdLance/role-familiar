<?php

namespace App\Actions\Familiars;

use App\Models\TargetRole;
use App\Models\User;
use App\Models\UserFamiliar;

class SummonPrototypeFamiliar
{
    public function __construct(private AssignQueueWyrmToTargetRole $assignQueueWyrm) {}

    public function __invoke(User $user, TargetRole $targetRole): UserFamiliar
    {
        return ($this->assignQueueWyrm)($user, $targetRole);
    }
}
