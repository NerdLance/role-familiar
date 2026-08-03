<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\RestoreTargetRole;
use App\Http\Requests\RestoreTargetRoleRequest;
use App\Models\TargetRole;
use Illuminate\Http\RedirectResponse;

class RestoreTargetRoleController extends Controller
{
    public function __invoke(RestoreTargetRoleRequest $request, TargetRole $targetRole, RestoreTargetRole $restoreTargetRole): RedirectResponse
    {
        $restoreTargetRole($targetRole);

        return to_route('target-roles.show', $targetRole);
    }
}
