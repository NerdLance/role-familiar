<?php

namespace App\Http\Requests;

use App\Enums\RoleAnalysisStatus;
use App\Enums\TargetRoleSourceType;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmTargetRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetRole = $this->route('targetRole');

        return $targetRole instanceof TargetRole && $this->user()->can('confirm', $targetRole);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $targetRole = $this->route('targetRole');

        if (! $targetRole instanceof TargetRole || $targetRole->source_type !== TargetRoleSourceType::JobListing) {
            return [];
        }

        $analysis = $targetRole->analyses()->completed()->latest('id')->first();
        $proposal = $analysis?->structured_result;
        $hasUncertainty = is_array($proposal)
            && (! empty($proposal['assumptions']) || ! empty($proposal['ambiguities']));

        return $hasUncertainty
            ? ['acknowledge_uncertainty' => ['accepted']]
            : [];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var TargetRole $targetRole */
            $targetRole = $this->route('targetRole');

            if (blank($targetRole->title)) {
                $validator->errors()->add('title', 'A title is required before confirmation.');
            }

            if (blank($targetRole->role_summary)) {
                $validator->errors()->add('role_summary', 'A role summary is required before confirmation.');
            }

            if (! $targetRole->responsibilities()->where('content', '!=', '')->exists()) {
                $validator->errors()->add('responsibilities', 'Add at least one responsibility before confirmation.');
            }

            if (! $targetRole->competencies()->where('name', '!=', '')->exists()) {
                $validator->errors()->add('competencies', 'Add at least one competency before confirmation.');
            }

            if ($targetRole->source_type === TargetRoleSourceType::JobListing) {
                $analysis = $targetRole->analyses()->latest('id')->first();

                if (! $analysis instanceof RoleAnalysis
                    || $analysis->status !== RoleAnalysisStatus::Completed
                    || $analysis->review()->doesntExist()) {
                    $validator->errors()->add('analysis', 'Save your analysis review before confirmation.');

                    return;
                }

                if (! is_string($targetRole->original_job_listing)
                    || ! hash_equals($analysis->source_hash, RoleAnalysis::sourceHash($targetRole->original_job_listing))) {
                    $validator->errors()->add('analysis', 'The listing changed. Request a new analysis before confirmation.');
                }
            }
        }];
    }
}
