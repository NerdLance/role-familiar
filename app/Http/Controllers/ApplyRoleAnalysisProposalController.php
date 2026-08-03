<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\ApplyRoleAnalysisProposal;
use App\Http\Requests\ApplyRoleAnalysisProposalRequest;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use Illuminate\Http\RedirectResponse;

class ApplyRoleAnalysisProposalController extends Controller
{
    public function __invoke(
        ApplyRoleAnalysisProposalRequest $request,
        TargetRole $targetRole,
        RoleAnalysis $analysis,
        ApplyRoleAnalysisProposal $applyProposal,
    ): RedirectResponse {
        $applyProposal($request->user(), $targetRole, $analysis, $request->validated());

        return to_route('target-roles.show', $targetRole)
            ->with('success', 'Your review corrections were saved.');
    }
}
