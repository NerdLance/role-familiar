<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\RequestJobListingAnalysis;
use App\Http\Requests\RequestJobListingAnalysisRequest;
use App\Models\TargetRole;
use Illuminate\Http\RedirectResponse;

class RequestJobListingAnalysisController extends Controller
{
    public function __invoke(
        RequestJobListingAnalysisRequest $request,
        TargetRole $targetRole,
        RequestJobListingAnalysis $requestAnalysis,
    ): RedirectResponse {
        $requestAnalysis($request->user(), $targetRole, $request->validated('job_listing'));

        return to_route('target-roles.show', $targetRole)
            ->with('success', 'Job-listing analysis queued.');
    }
}
