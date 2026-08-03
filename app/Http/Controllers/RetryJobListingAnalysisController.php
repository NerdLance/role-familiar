<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\RequestJobListingAnalysis;
use App\Http\Requests\RetryJobListingAnalysisRequest;
use App\Models\TargetRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RetryJobListingAnalysisController extends Controller
{
    public function __invoke(
        RetryJobListingAnalysisRequest $request,
        TargetRole $targetRole,
        RequestJobListingAnalysis $requestAnalysis,
    ): RedirectResponse {
        if (! is_string($targetRole->original_job_listing)) {
            throw ValidationException::withMessages(['analysis' => 'The original listing is unavailable.']);
        }

        $requestAnalysis($request->user(), $targetRole, $targetRole->original_job_listing);

        return to_route('target-roles.show', $targetRole)
            ->with('success', 'A new analysis attempt was queued.');
    }
}
