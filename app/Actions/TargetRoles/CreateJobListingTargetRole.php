<?php

namespace App\Actions\TargetRoles;

use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Models\TargetRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateJobListingTargetRole
{
    public function __construct(private readonly RequestJobListingAnalysis $requestAnalysis) {}

    /** @param array<string, mixed> $attributes */
    public function __invoke(User $user, array $attributes): TargetRole
    {
        return DB::transaction(function () use ($user, $attributes): TargetRole {
            $targetRole = $user->targetRoles()->create([
                'status' => TargetRoleStatus::Draft,
                'source_type' => TargetRoleSourceType::JobListing,
                'company_name' => $attributes['company_name'] ?? null,
                'interview_at' => $attributes['interview_at'] ?? null,
                'biggest_concern' => $attributes['biggest_concern'] ?? null,
            ]);

            ($this->requestAnalysis)($user, $targetRole, $attributes['job_listing']);

            return $targetRole->refresh();
        });
    }
}
