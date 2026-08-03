<?php

namespace App\Actions\TargetRoles;

use App\Ai\Agents\JobListingAnalyst;
use App\Enums\RoleAnalysisStatus;
use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Jobs\AnalyzeJobListing;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestJobListingAnalysis
{
    /** @throws ValidationException */
    public function __invoke(User $user, TargetRole $targetRole, string $listing): RoleAnalysis
    {
        Gate::forUser($user)->authorize('analyze', $targetRole);

        $validated = Validator::make(['job_listing' => $listing], [
            'job_listing' => ['required', 'string', 'min:100', 'max:50000'],
        ])->validate();
        $listing = $validated['job_listing'];

        if (mb_strlen(trim($listing)) < 100) {
            throw ValidationException::withMessages([
                'job_listing' => 'The job listing must contain at least 100 characters of content.',
            ]);
        }

        $sourceHash = RoleAnalysis::sourceHash($listing);
        $wasCreated = false;

        $analysis = DB::transaction(function () use ($user, $targetRole, $listing, $sourceHash, &$wasCreated): RoleAnalysis {
            $lockedRole = TargetRole::query()->lockForUpdate()->findOrFail($targetRole->id);
            Gate::forUser($user)->authorize('analyze', $lockedRole);

            if ($lockedRole->source_type !== TargetRoleSourceType::JobListing) {
                throw ValidationException::withMessages([
                    'job_listing' => 'This role does not support pasted job-listing analysis.',
                ]);
            }

            $existingAnalysis = $lockedRole->analyses()
                ->where('source_hash', $sourceHash)
                ->whereIn('status', [RoleAnalysisStatus::Pending, RoleAnalysisStatus::Processing])
                ->latest('id')
                ->first();

            if ($existingAnalysis instanceof RoleAnalysis) {
                return $existingAnalysis;
            }

            $lockedRole->update([
                'original_job_listing' => $listing,
                'status' => TargetRoleStatus::AnalysisPending,
                'confirmed_at' => null,
                'archived_at' => null,
            ]);

            $wasCreated = true;

            return $lockedRole->analyses()->create([
                'status' => RoleAnalysisStatus::Pending,
                'prompt_version' => JobListingAnalyst::PROMPT_VERSION,
                'provider' => (string) config('ai.role_analysis.provider', 'openai'),
                'model' => (string) (config('ai.role_analysis.model') ?: 'unconfigured'),
                'source_hash' => $sourceHash,
            ]);
        });

        if ($wasCreated) {
            AnalyzeJobListing::dispatch($analysis->id)->afterCommit();
        }

        return $analysis;
    }
}
