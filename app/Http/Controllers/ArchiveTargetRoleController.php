<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\ArchiveTargetRole;
use App\Http\Requests\ArchiveTargetRoleRequest;
use App\Models\TargetRole;
use Illuminate\Http\RedirectResponse;

class ArchiveTargetRoleController extends Controller
{
    public function __invoke(ArchiveTargetRoleRequest $request, TargetRole $targetRole, ArchiveTargetRole $archiveTargetRole): RedirectResponse
    {
        $archiveTargetRole($targetRole);

        return to_route('target-roles.show', $targetRole);
    }
}
