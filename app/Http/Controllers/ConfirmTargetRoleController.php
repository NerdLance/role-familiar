<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\ConfirmTargetRole;
use App\Http\Requests\ConfirmTargetRoleRequest;
use App\Models\TargetRole;
use Illuminate\Http\RedirectResponse;

class ConfirmTargetRoleController extends Controller
{
    public function __invoke(ConfirmTargetRoleRequest $request, TargetRole $targetRole, ConfirmTargetRole $confirmTargetRole): RedirectResponse
    {
        $confirmTargetRole($targetRole);

        return to_route('target-roles.show', $targetRole);
    }
}
