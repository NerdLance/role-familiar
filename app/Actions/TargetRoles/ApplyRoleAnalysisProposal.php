<?php

namespace App\Actions\TargetRoles;

use App\Enums\CompetencyCategory;
use App\Enums\DataSource;
use App\Enums\RoleAnalysisStatus;
use App\Enums\TargetRoleStatus;
use App\Http\Requests\ApplyRoleAnalysisProposalRequest;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplyRoleAnalysisProposal
{
    public function __construct(
        private readonly SyncRoleResponsibilities $syncResponsibilities,
        private readonly SyncRoleCompetencies $syncCompetencies,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $user, TargetRole $targetRole, RoleAnalysis $analysis, array $attributes): TargetRole
    {
        Gate::forUser($user)->authorize('applyAnalysis', $targetRole);
        $attributes = Validator::make($attributes, ApplyRoleAnalysisProposalRequest::rulesFor($targetRole->id))->validate();

        return DB::transaction(function () use ($user, $targetRole, $analysis, $attributes): TargetRole {
            $lockedRole = TargetRole::query()->whereKey($targetRole)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('applyAnalysis', $lockedRole);
            $lockedAnalysis = RoleAnalysis::query()->whereKey($analysis)->lockForUpdate()->firstOrFail();

            if ($lockedAnalysis->target_role_id !== $lockedRole->id || $lockedAnalysis->status !== RoleAnalysisStatus::Completed) {
                throw ValidationException::withMessages(['analysis' => 'This analysis is not available for review.']);
            }

            if (! is_string($lockedRole->original_job_listing)
                || ! hash_equals($lockedAnalysis->source_hash, RoleAnalysis::sourceHash($lockedRole->original_job_listing))) {
                throw ValidationException::withMessages(['analysis' => 'The listing changed. Request a new analysis before saving this review.']);
            }

            $proposal = $lockedAnalysis->structured_result;

            if (! is_array($proposal)) {
                throw ValidationException::withMessages(['analysis' => 'This analysis has no reviewable proposal.']);
            }

            $responsibilities = $this->responsibilities($attributes, $proposal);
            $competencies = $this->competencies($attributes, $proposal);
            $ambiguities = $this->decisions($attributes, $proposal, 'ambiguities');
            $assumptions = $this->decisions($attributes, $proposal, 'assumptions');

            $lockedRole->update([
                'status' => TargetRoleStatus::ReviewRequired,
                'title' => $attributes['title'],
                'company_name' => $attributes['company_name'] ?? null,
                'seniority' => $attributes['seniority'],
                'role_summary' => $attributes['role_summary'],
                'interview_at' => $attributes['interview_at'] ?? null,
                'biggest_concern' => $attributes['biggest_concern'] ?? null,
            ]);

            $savedResponsibilities = ($this->syncResponsibilities)($lockedRole, $responsibilities);
            $savedCompetencies = ($this->syncCompetencies)($lockedRole, $competencies);
            $reviewedPayload = [
                'title' => $lockedRole->title,
                'company_name' => $lockedRole->company_name,
                'seniority' => $lockedRole->seniority?->value,
                'role_summary' => $lockedRole->role_summary,
                'interview_at' => $lockedRole->interview_at?->toIso8601String(),
                'biggest_concern' => $lockedRole->biggest_concern,
                'responsibilities' => $savedResponsibilities->values()->map(fn ($row, int $index): array => [
                    'id' => $row->id,
                    ...$responsibilities[$index]['review'],
                ])->all(),
                'competencies' => $savedCompetencies->values()->map(fn ($row, int $index): array => [
                    'id' => $row->id,
                    ...$competencies[$index]['review'],
                ])->all(),
                'interview_focus_areas' => $this->interviewFocusAreas($proposal),
                'clarifications' => $this->clarifications($proposal, $ambiguities),
            ];

            $lockedAnalysis->review()->updateOrCreate([], [
                'reviewed_payload' => $reviewedPayload,
                'ambiguity_responses' => $ambiguities,
                'assumption_decisions' => $assumptions,
                'applied_at' => now(),
            ]);

            return $lockedRole->refresh()->load(['responsibilities', 'competencies']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $proposal
     * @return array<int, array{id: int|null, content: string, priority: string, source: DataSource, source_evidence: string|null, user_confirmed_at: null, review: array{proposal_index: int|null, content: string, priority: string, evidence: string|null, confidence: int|null, inferred: bool, source: string}}>
     */
    private function responsibilities(array $attributes, array $proposal): array
    {
        $proposalRows = is_array($proposal['responsibilities'] ?? null) ? $proposal['responsibilities'] : [];

        $responsibilityRows = $attributes['responsibilities'] ?? null;

        if (! is_array($responsibilityRows)) {
            throw ValidationException::withMessages(['responsibilities' => 'Responsibilities must be provided as a list.']);
        }

        $normalized = [];

        foreach ($responsibilityRows as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['responsibilities' => 'Each responsibility must be a valid row.']);
            }

            $index = $row['proposal_index'] ?? null;
            $original = is_int($index) && is_array($proposalRows[$index] ?? null) ? $proposalRows[$index] : null;

            if (is_int($index) && $original === null) {
                throw ValidationException::withMessages(['responsibilities' => 'A responsibility no longer matches this proposal.']);
            }

            $corrected = $original !== null
                && ($row['content'] !== $original['content'] || $row['priority'] !== $original['priority']);
            $source = $original === null
                ? DataSource::User
                : ($corrected ? DataSource::UserCorrected : DataSource::from($original['source']));
            $evidence = is_string($original['evidence'] ?? null) ? $original['evidence'] : null;
            $confidence = is_int($original['confidence'] ?? null) ? $original['confidence'] : null;

            $normalized[] = [
                'id' => $row['id'] ?? null,
                'content' => $row['content'],
                'priority' => $row['priority'],
                'source' => $source,
                'source_evidence' => $evidence,
                'user_confirmed_at' => null,
                'review' => [
                    'proposal_index' => $index,
                    'content' => $row['content'],
                    'priority' => $row['priority'],
                    'evidence' => $evidence,
                    'confidence' => $confidence,
                    'inferred' => $source === DataSource::AiInferred,
                    'source' => $source->value,
                ],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $proposal
     * @return array<int, array{id: int|null, name: string, category: string, priority: string, source: DataSource, source_evidence: string|null, user_confidence: int|null, user_confirmed_at: null, review: array{proposal_type: string|null, proposal_index: int|null, name: string, category: string, priority: string, evidence: string|null, confidence: int|null, inferred: bool, source: string, user_confidence: int|null}}>
     */
    private function competencies(array $attributes, array $proposal): array
    {
        $competencies = is_array($proposal['competencies'] ?? null) ? $proposal['competencies'] : [];
        $technologies = is_array($proposal['technologies_and_tools'] ?? null) ? $proposal['technologies_and_tools'] : [];
        $seen = [];
        $normalized = [];

        $competencyRows = $attributes['competencies'] ?? null;

        if (! is_array($competencyRows)) {
            throw ValidationException::withMessages(['competencies' => 'Competencies must be provided as a list.']);
        }

        foreach ($competencyRows as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['competencies' => 'Each competency must be a valid row.']);
            }

            $type = $row['proposal_type'] ?? null;
            $index = $row['proposal_index'] ?? null;
            $proposalRows = $type === 'technology' ? $technologies : $competencies;
            $original = is_int($index) && in_array($type, ['competency', 'technology'], true) && is_array($proposalRows[$index] ?? null)
                ? $proposalRows[$index]
                : null;

            if (($type !== null || $index !== null) && $original === null) {
                throw ValidationException::withMessages(['competencies' => 'A competency no longer matches this proposal.']);
            }

            $originalCategory = $type === 'technology' ? CompetencyCategory::Tooling->value : ($original['category'] ?? null);
            $corrected = $original !== null && (
                $row['name'] !== $original['name']
                || $row['category'] !== $originalCategory
                || $row['priority'] !== $original['priority']
            );
            $source = $original === null
                ? DataSource::User
                : ($corrected ? DataSource::UserCorrected : DataSource::from($original['source']));
            $evidence = is_string($original['evidence'] ?? null) ? $original['evidence'] : null;
            $confidence = is_int($original['confidence'] ?? null) ? $original['confidence'] : null;
            $duplicateKey = Str::lower($row['category'].'|'.$row['name']);

            if (isset($seen[$duplicateKey])) {
                continue;
            }

            $seen[$duplicateKey] = true;
            $normalized[] = [
                'id' => $row['id'] ?? null,
                'name' => $row['name'],
                'category' => $row['category'],
                'priority' => $row['priority'],
                'source_evidence' => $evidence,
                'source' => $source,
                'user_confidence' => $row['user_confidence'] ?? null,
                'user_confirmed_at' => null,
                'review' => [
                    'proposal_type' => $type,
                    'proposal_index' => $index,
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'priority' => $row['priority'],
                    'evidence' => $evidence,
                    'confidence' => $confidence,
                    'inferred' => $source === DataSource::AiInferred,
                    'source' => $source->value,
                    'user_confidence' => $row['user_confidence'] ?? null,
                ],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $proposal
     * @return array<int, array<string, mixed>>
     */
    private function decisions(array $attributes, array $proposal, string $key): array
    {
        $proposalRows = is_array($proposal[$key] ?? null) ? $proposal[$key] : [];

        $decisionRows = $attributes[$key] ?? null;

        if (! is_array($decisionRows)) {
            throw ValidationException::withMessages([$key => 'Review decisions must be provided as a list.']);
        }

        $normalized = [];

        foreach ($decisionRows as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([$key => 'Each review decision must be a valid row.']);
            }

            if (! is_array($proposalRows[$row['index']] ?? null)) {
                throw ValidationException::withMessages([$key => 'A review item no longer matches this proposal.']);
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array<int, array{name: string, reason: string}>
     */
    private function interviewFocusAreas(array $proposal): array
    {
        $focusAreas = is_array($proposal['interview_focus_areas'] ?? null)
            ? $proposal['interview_focus_areas']
            : [];
        $normalized = [];

        foreach ($focusAreas as $focusArea) {
            if (! is_array($focusArea)
                || ! is_string($focusArea['focus'] ?? null)
                || ! is_string($focusArea['rationale'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'name' => $focusArea['focus'],
                'reason' => $focusArea['rationale'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @param  array<int, array<string, mixed>>  $decisions
     * @return array<int, array{question: string, reason: string, status: string, answer: string|null}>
     */
    private function clarifications(array $proposal, array $decisions): array
    {
        $ambiguities = is_array($proposal['ambiguities'] ?? null) ? $proposal['ambiguities'] : [];
        $normalized = [];

        foreach ($decisions as $decision) {
            $index = $decision['index'] ?? null;
            $ambiguity = is_int($index) && is_array($ambiguities[$index] ?? null)
                ? $ambiguities[$index]
                : null;

            if ($ambiguity === null
                || ! is_string($ambiguity['question'] ?? null)
                || ! is_string($ambiguity['reason'] ?? null)
                || ! is_string($decision['status'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'question' => $ambiguity['question'],
                'reason' => $ambiguity['reason'],
                'status' => $decision['status'],
                'answer' => is_string($decision['answer'] ?? null) ? $decision['answer'] : null,
            ];
        }

        return $normalized;
    }
}
