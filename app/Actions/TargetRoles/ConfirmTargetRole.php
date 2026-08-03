<?php

namespace App\Actions\TargetRoles;

use App\Enums\RoleAnalysisStatus;
use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmTargetRole
{
    public function __invoke(TargetRole $targetRole, bool $uncertaintyAcknowledged = false): TargetRole
    {
        return DB::transaction(function () use ($targetRole, $uncertaintyAcknowledged): TargetRole {
            User::query()->whereKey($targetRole->user_id)->lockForUpdate()->firstOrFail();
            $lockedTargetRole = TargetRole::query()->whereKey($targetRole)->lockForUpdate()->firstOrFail();

            $errors = [];

            if (blank($lockedTargetRole->title)) {
                $errors['title'] = 'A title is required before confirmation.';
            }

            if (! $lockedTargetRole->responsibilities()->whereNotNull('content')->where('content', '!=', '')->exists()) {
                $errors['responsibilities'] = 'Add at least one responsibility before confirmation.';
            }

            if (! $lockedTargetRole->competencies()->whereNotNull('name')->where('name', '!=', '')->exists()) {
                $errors['competencies'] = 'Add at least one competency before confirmation.';
            }

            if ($lockedTargetRole->source_type === TargetRoleSourceType::JobListing) {
                $analysis = $lockedTargetRole->analyses()->latest('id')->first();

                if (! $analysis instanceof RoleAnalysis
                    || $analysis->status !== RoleAnalysisStatus::Completed
                    || $analysis->review()->doesntExist()
                    || ! is_string($lockedTargetRole->original_job_listing)
                    || ! hash_equals($analysis->source_hash, RoleAnalysis::sourceHash($lockedTargetRole->original_job_listing))) {
                    $errors['analysis'] = 'Save a current analysis review before confirmation.';
                } elseif ($this->hasUncertainty($analysis) && ! $uncertaintyAcknowledged) {
                    $errors['acknowledge_uncertainty'] = 'Acknowledge the visible assumptions and ambiguities before confirmation.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            TargetRole::query()
                ->ownedBy($lockedTargetRole->user)
                ->active()
                ->whereKeyNot($lockedTargetRole->id)
                ->lockForUpdate()
                ->get()
                ->each->update([
                    'status' => TargetRoleStatus::Archived,
                    'archived_at' => now(),
                ]);

            $confirmedAt = $lockedTargetRole->confirmed_at ?? now();
            $lockedTargetRole->update([
                'status' => TargetRoleStatus::Confirmed,
                'confirmed_at' => $confirmedAt,
                'archived_at' => null,
            ]);
            $lockedTargetRole->responsibilities()->update(['user_confirmed_at' => $confirmedAt]);
            $lockedTargetRole->competencies()->update(['user_confirmed_at' => $confirmedAt]);

            return $lockedTargetRole->refresh()->load(['responsibilities', 'competencies']);
        });
    }

    private function hasUncertainty(RoleAnalysis $analysis): bool
    {
        $proposal = $analysis->structured_result;

        return is_array($proposal)
            && (! empty($proposal['assumptions']) || ! empty($proposal['ambiguities']));
    }
}
