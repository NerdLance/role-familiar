<?php

namespace App\Http\Controllers;

use App\Actions\TargetRoles\CreateTargetRole;
use App\Actions\TargetRoles\DeleteTargetRole;
use App\Actions\TargetRoles\UpdateTargetRole;
use App\Enums\CompetencyCategory;
use App\Enums\Priority;
use App\Enums\Seniority;
use App\Enums\TargetRoleStatus;
use App\Http\Requests\DeleteTargetRoleRequest;
use App\Http\Requests\StoreTargetRoleRequest;
use App\Http\Requests\UpdateTargetRoleRequest;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use App\Support\Familiars\FamiliarAssetCatalog;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TargetRoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TargetRole::class);

        $targetRoles = $request->user()->targetRoles()
            ->withCount(['responsibilities', 'competencies'])
            ->latest('updated_at')
            ->get()
            ->map(fn (TargetRole $targetRole): array => [
                ...$this->publicRole($targetRole),
                'responsibilities_count' => $targetRole->responsibilities_count,
                'competencies_count' => $targetRole->competencies_count,
            ]);

        return Inertia::render('target-roles/index', ['targetRoles' => $targetRoles]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TargetRole::class);

        return Inertia::render('target-roles/create', ['options' => $this->options()]);
    }

    public function store(StoreTargetRoleRequest $request, CreateTargetRole $createTargetRole): RedirectResponse
    {
        $targetRole = $createTargetRole($request->user(), $request->validated());

        return to_route('target-roles.show', $targetRole);
    }

    public function show(Request $request, TargetRole $targetRole, FamiliarAssetCatalog $catalog): Response
    {
        Gate::authorize('view', $targetRole);
        $targetRole->load(['responsibilities', 'competencies']);
        $analysis = $this->reviewAnalysis($targetRole);

        return Inertia::render('target-roles/show', [
            'targetRole' => $this->publicProfile($targetRole),
            'analysis' => $analysis === null ? null : $this->publicAnalysis($analysis),
            'options' => $this->options(),
            'queueWyrm' => $catalog->roleWorkflowVisual($request->user()->familiar()->first()?->stage),
            'celebrateConfirmation' => $request->session()->pull('role_confirmed', false),
        ]);
    }

    public function edit(TargetRole $targetRole): Response
    {
        Gate::authorize('update', $targetRole);
        $targetRole->load(['responsibilities', 'competencies']);

        return Inertia::render('target-roles/edit', [
            'targetRole' => $this->publicProfile($targetRole),
            'options' => $this->options(),
        ]);
    }

    public function update(UpdateTargetRoleRequest $request, TargetRole $targetRole, UpdateTargetRole $updateTargetRole): RedirectResponse
    {
        $updateTargetRole($targetRole, $request->validated());

        return to_route('target-roles.show', $targetRole);
    }

    public function destroy(DeleteTargetRoleRequest $request, TargetRole $targetRole, DeleteTargetRole $deleteTargetRole): RedirectResponse
    {
        $deleteTargetRole($targetRole);

        return to_route('target-roles.index');
    }

    /** @return array<string, mixed> */
    private function publicRole(TargetRole $targetRole): array
    {
        return [
            'public_id' => $targetRole->public_id,
            'status' => $targetRole->status->value,
            'source_type' => $targetRole->source_type->value,
            'title' => $targetRole->title,
            'company_name' => $targetRole->company_name,
            'seniority' => $targetRole->seniority?->value,
            'role_summary' => $targetRole->role_summary,
            'interview_at' => $targetRole->interview_at?->toIso8601String(),
            'biggest_concern' => $targetRole->biggest_concern,
            'confirmed_at' => $targetRole->confirmed_at?->toIso8601String(),
            'archived_at' => $targetRole->archived_at?->toIso8601String(),
            'is_active' => $targetRole->isActive(),
            'updated_at' => $targetRole->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function publicProfile(TargetRole $targetRole): array
    {
        return [
            ...$this->publicRole($targetRole),
            'responsibilities' => $targetRole->responsibilities->map(fn ($responsibility): array => [
                'id' => $responsibility->id,
                'content' => $responsibility->content,
                'priority' => $responsibility->priority->value,
                'source' => $responsibility->source->value,
                'source_evidence' => $responsibility->source_evidence,
            ])->values(),
            'competencies' => $targetRole->competencies->map(fn ($competency): array => [
                'id' => $competency->id,
                'name' => $competency->name,
                'category' => $competency->category->value,
                'priority' => $competency->priority->value,
                'source_evidence' => $competency->source_evidence,
                'user_confidence' => $competency->user_confidence,
                'source' => $competency->source->value,
            ])->values(),
        ];
    }

    private function reviewAnalysis(TargetRole $targetRole): ?RoleAnalysis
    {
        if (! in_array($targetRole->status, [
            TargetRoleStatus::AnalysisPending,
            TargetRoleStatus::AnalysisFailed,
            TargetRoleStatus::ReviewRequired,
        ], true)) {
            return null;
        }

        return $targetRole->analyses()->with('review')->latest('id')->first();
    }

    /** @return array<string, mixed> */
    private function publicAnalysis(RoleAnalysis $analysis): array
    {
        return [
            'public_id' => $analysis->public_id,
            'status' => $analysis->status->value,
            'failure_message' => $analysis->isFailed()
                ? ($analysis->failure_summary ?? 'The analysis could not be completed.')
                : null,
            'proposal' => $analysis->isCompleted() ? $analysis->structured_result : null,
            'review' => $analysis->review === null
                ? null
                : [
                    'reviewed_payload' => $analysis->review->reviewed_payload,
                    'ambiguity_responses' => $analysis->review->ambiguity_responses,
                    'assumption_decisions' => $analysis->review->assumption_decisions,
                    'applied_at' => $analysis->review->applied_at->toIso8601String(),
                ],
        ];
    }

    /** @return array<string, array<int, array{value: string, label: string}>> */
    private function options(): array
    {
        return [
            'seniorities' => $this->enumOptions(Seniority::cases()),
            'priorities' => $this->enumOptions(Priority::cases()),
            'competencyCategories' => $this->enumOptions(CompetencyCategory::cases()),
        ];
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return collect($cases)->map(fn (BackedEnum $case): array => [
            'value' => (string) $case->value,
            'label' => Str::of((string) $case->value)->replace('_', ' ')->headline()->toString(),
        ])->all();
    }
}
