<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\CreateJobListingTargetRole;
use App\Http\Requests\StoreJobListingTargetRoleRequest;
use App\Models\TargetRole;
use App\Support\Familiars\FamiliarAssetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JobListingTargetRoleController extends Controller
{
    public function create(Request $request, FamiliarAssetCatalog $catalog): Response
    {
        Gate::authorize('create', TargetRole::class);

        return Inertia::render('target-roles/job-listing/create', [
            'queueWyrm' => $catalog->roleWorkflowVisual(null),
        ]);
    }

    public function store(StoreJobListingTargetRoleRequest $request, CreateJobListingTargetRole $createTargetRole): RedirectResponse
    {
        $targetRole = $createTargetRole($request->user(), $request->validated());

        return to_route('target-roles.show', $targetRole)
            ->with('success', 'Job-listing analysis queued.');
    }
}
