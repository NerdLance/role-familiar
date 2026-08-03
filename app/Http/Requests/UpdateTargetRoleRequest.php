<?php

namespace App\Http\Requests;

use App\Models\TargetRole;

class UpdateTargetRoleRequest extends TargetRoleProfileRequest
{
    public function authorize(): bool
    {
        $targetRole = $this->route('targetRole');

        return $targetRole instanceof TargetRole && $this->user()->can('update', $targetRole);
    }
}
