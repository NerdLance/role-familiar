<?php

namespace App\Http\Requests;

use App\Models\TargetRole;

class StoreTargetRoleRequest extends TargetRoleProfileRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TargetRole::class);
    }
}
