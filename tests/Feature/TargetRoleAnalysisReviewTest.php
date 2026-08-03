<?php

use App\Enums\DataSource;
use App\Enums\FamiliarStage;
use App\Enums\RoleAnalysisStatus;
use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Jobs\AnalyzeJobListing;
use App\Models\FamiliarProgressionEvent;
use App\Models\FamiliarQuestProgress;
use App\Models\RoleAnalysis;
use App\Models\RoleAnalysisReview;
use App\Models\TargetRole;
use App\Models\User;
use App\Models\UserFamiliar;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function analysisReviewListing(): string
{
    return 'Example Company seeks a Senior Backend Engineer to design Laravel APIs, own service architecture, mentor peers, and explain technical tradeoffs. The successful candidate will deliver reliable backend systems.';
}

/** @return array<string, mixed> */
function analysisReviewProposal(?string $company = 'Example Company'): array
{
    return [
        'normalized_title' => 'Senior Backend Engineer',
        'company_name' => $company,
        'seniority' => 'senior',
        'role_summary' => 'Own reliable backend services and technical direction.',
        'responsibilities' => [[
            'content' => 'Design and maintain backend services',
            'priority' => 'high',
            'evidence' => 'design Laravel APIs',
            'source' => 'job_listing',
            'confidence' => 94,
        ]],
        'competencies' => [[
            'name' => 'API architecture',
            'category' => 'technical',
            'priority' => 'high',
            'evidence' => 'own service architecture',
            'source' => 'job_listing',
            'confidence' => 91,
        ]],
        'technologies_and_tools' => [[
            'name' => 'Laravel',
            'priority' => 'high',
            'evidence' => 'Laravel APIs',
            'source' => 'job_listing',
            'confidence' => 99,
        ]],
        'interview_focus_areas' => [[
            'focus' => 'System design',
            'rationale' => 'Architecture ownership is emphasized.',
            'priority' => 'high',
            'evidence' => 'own service architecture',
            'source' => 'job_listing',
            'confidence' => 84,
        ]],
        'ambiguities' => [[
            'area' => 'responsibility',
            'question' => 'Does this role directly manage engineers?',
            'reason' => 'Mentoring is explicit but people management is not.',
        ]],
        'assumptions' => [[
            'area' => 'seniority',
            'statement' => 'The role likely includes technical leadership.',
            'reason' => 'Architecture ownership and mentoring are emphasized.',
        ]],
        'overall_extraction_confidence' => 90,
    ];
}

/** @return array{0: TargetRole, 1: RoleAnalysis} */
function reviewableAnalysis(User $user, ?array $proposal = null): array
{
    $listing = analysisReviewListing();
    $role = TargetRole::factory()->for($user)->reviewRequired()->create([
        'source_type' => TargetRoleSourceType::JobListing,
        'title' => null,
        'company_name' => null,
        'seniority' => null,
        'role_summary' => null,
        'original_job_listing' => $listing,
    ]);
    $analysis = RoleAnalysis::factory()->for($role)->completed()->create([
        'source_hash' => RoleAnalysis::sourceHash($listing),
        'structured_result' => $proposal ?? analysisReviewProposal(),
        'assumptions' => ($proposal ?? analysisReviewProposal())['assumptions'],
    ]);

    return [$role, $analysis];
}

/** @return array<string, mixed> */
function reviewedProposalPayload(): array
{
    return [
        'title' => 'Principal Backend Engineer',
        'company_name' => 'Corrected Company',
        'seniority' => 'principal',
        'role_summary' => 'Lead reliable service architecture.',
        'interview_at' => '2026-09-10T14:30',
        'biggest_concern' => 'Explaining architecture tradeoffs',
        'responsibilities' => [[
            'proposal_index' => 0,
            'content' => 'Lead and maintain backend services',
            'priority' => 'critical',
            'evidence' => 'client value is ignored',
            'confidence' => 1,
            'inferred' => true,
        ]],
        'competencies' => [
            [
                'proposal_type' => 'competency',
                'proposal_index' => 0,
                'name' => 'API architecture',
                'category' => 'technical',
                'priority' => 'high',
                'evidence' => 'client value is ignored',
                'confidence' => 1,
                'inferred' => true,
                'user_confidence' => 4,
            ],
            [
                'proposal_type' => 'technology',
                'proposal_index' => 0,
                'name' => 'Laravel',
                'category' => 'tooling',
                'priority' => 'high',
                'evidence' => null,
                'confidence' => 1,
                'inferred' => true,
                'user_confidence' => 3,
            ],
        ],
        'ambiguities' => [[
            'index' => 0,
            'status' => 'answered',
            'answer' => 'No direct reports.',
        ]],
        'assumptions' => [[
            'index' => 0,
            'status' => 'corrected',
            'correction' => 'The role owns architecture but not team management.',
        ]],
    ];
}

test('the job listing entry page is private and exposes only public familiar assets', function () {
    $user = User::factory()->create();

    $this->get(route('target-roles.job-listing.create'))->assertRedirect(route('login'));

    $this->actingAs($user)->get(route('target-roles.job-listing.create'))->assertInertia(fn (Assert $page) => $page
        ->component('target-roles/job-listing/create')
        ->where('queueWyrm.name', 'Queue Wyrm')
        ->has('queueWyrm.idle')
        ->has('queueWyrm.overlays.thinking')
        ->missing('job_listing')
        ->missing('analysis'));
});

test('submitting a listing creates known context and queues analysis', function () {
    Queue::fake();
    $user = User::factory()->create();
    $listing = "\n".analysisReviewListing()."\n";

    $response = $this->actingAs($user)->post(route('target-roles.job-listing.store'), [
        'job_listing' => $listing,
        'company_name' => '  Known Company  ',
        'interview_at' => '2026-09-10T14:30',
        'biggest_concern' => '  System design  ',
    ]);

    $role = TargetRole::query()->sole();
    $response->assertRedirect(route('target-roles.show', $role));

    expect($role->source_type)->toBe(TargetRoleSourceType::JobListing)
        ->and($role->status)->toBe(TargetRoleStatus::AnalysisPending)
        ->and($role->company_name)->toBe('Known Company')
        ->and($role->biggest_concern)->toBe('System design')
        ->and($role->original_job_listing)->toBe($listing)
        ->and($role->analyses()->count())->toBe(1);

    Queue::assertPushed(AnalyzeJobListing::class, 1);
});

test('pending failed and completed pages expose controlled status and review props', function () {
    $user = User::factory()->create();
    $pendingRole = TargetRole::factory()->for($user)->analysisPending()->create(['original_job_listing' => analysisReviewListing()]);
    RoleAnalysis::factory()->for($pendingRole)->pending()->create(['source_hash' => RoleAnalysis::sourceHash(analysisReviewListing())]);

    $this->actingAs($user)->get(route('target-roles.show', $pendingRole))->assertInertia(fn (Assert $page) => $page
        ->component('target-roles/show')
        ->where('targetRole.status', 'analysis_pending')
        ->where('analysis.status', 'pending')
        ->where('analysis.proposal', null)
        ->missing('targetRole.original_job_listing')
        ->missing('analysis.provider')
        ->missing('analysis.model')
        ->missing('analysis.source_hash')
        ->missing('analysis.usage_metadata'));

    $failedRole = TargetRole::factory()->for($user)->create([
        'status' => TargetRoleStatus::AnalysisFailed,
        'source_type' => TargetRoleSourceType::JobListing,
        'original_job_listing' => analysisReviewListing(),
    ]);
    RoleAnalysis::factory()->for($failedRole)->failed()->create(['source_hash' => RoleAnalysis::sourceHash(analysisReviewListing())]);

    $this->actingAs($user)->get(route('target-roles.show', $failedRole))->assertInertia(fn (Assert $page) => $page
        ->where('analysis.status', 'failed')
        ->where('analysis.failure_message', 'The analysis provider was temporarily unavailable.')
        ->missing('analysis.failure_category'));

    [$reviewRole] = reviewableAnalysis($user);
    $this->actingAs($user)->get(route('target-roles.show', $reviewRole))->assertInertia(fn (Assert $page) => $page
        ->where('analysis.status', 'completed')
        ->where('analysis.proposal.responsibilities.0.evidence', 'design Laravel APIs')
        ->where('analysis.proposal.responsibilities.0.confidence', 94)
        ->where('analysis.proposal.assumptions.0.statement', 'The role likely includes technical leadership.')
        ->where('analysis.proposal.ambiguities.0.question', 'Does this role directly manage engineers?')
        ->missing('analysis.provider')
        ->missing('analysis.usage_metadata')
        ->missing('readiness_score'));
});

test('retry appends a new analysis and preserves the failed attempt', function () {
    Queue::fake();
    $user = User::factory()->create();
    $role = TargetRole::factory()->for($user)->create([
        'status' => TargetRoleStatus::AnalysisFailed,
        'source_type' => TargetRoleSourceType::JobListing,
        'original_job_listing' => analysisReviewListing(),
    ]);
    $failed = RoleAnalysis::factory()->for($role)->failed()->create(['source_hash' => RoleAnalysis::sourceHash(analysisReviewListing())]);

    $this->actingAs($user)->post(route('target-roles.analysis.retry', $role))->assertRedirect(route('target-roles.show', $role));

    expect($role->refresh()->status)->toBe(TargetRoleStatus::AnalysisPending)
        ->and($role->analyses()->count())->toBe(2)
        ->and($failed->fresh()->status)->toBe(RoleAnalysisStatus::Failed);

    Queue::assertPushed(AnalyzeJobListing::class, 1);
});

test('review corrections persist authoritatively without confirming or mutating the analysis', function () {
    $user = User::factory()->create();
    [$role, $analysis] = reviewableAnalysis($user);
    $originalProposal = $analysis->structured_result;

    $this->actingAs($user)
        ->put(route('target-roles.analysis.review.update', [$role, $analysis]), reviewedProposalPayload())
        ->assertRedirect(route('target-roles.show', $role));

    $role->refresh();
    $review = RoleAnalysisReview::query()->sole();

    expect($role->status)->toBe(TargetRoleStatus::ReviewRequired)
        ->and($role->confirmed_at)->toBeNull()
        ->and($role->title)->toBe('Principal Backend Engineer')
        ->and($role->company_name)->toBe('Corrected Company')
        ->and($role->responsibilities()->sole()->source)->toBe(DataSource::UserCorrected)
        ->and($role->responsibilities()->sole()->source_evidence)->toBe('design Laravel APIs')
        ->and($role->competencies()->count())->toBe(2)
        ->and($role->competencies()->where('name', 'Laravel')->sole()->category->value)->toBe('tooling')
        ->and($review->reviewed_payload['interview_focus_areas'][0]['name'])->toBe('System design')
        ->and($review->reviewed_payload['clarifications'][0]['status'])->toBe('answered')
        ->and($review->ambiguity_responses[0]['answer'])->toBe('No direct reports.')
        ->and($review->assumption_decisions[0]['status'])->toBe('corrected')
        ->and($analysis->fresh()->structured_result)->toBe($originalProposal);
});

test('confirmation requires saved review and uncertainty acknowledgement without evolving the familiar', function () {
    $user = User::factory()->create();
    $familiar = UserFamiliar::factory()->for($user)->create(['stage' => FamiliarStage::Summoned]);
    [$role, $analysis] = reviewableAnalysis($user);

    $this->actingAs($user)->post(route('target-roles.confirm', $role))->assertSessionHasErrors(['analysis', 'acknowledge_uncertainty']);
    $this->actingAs($user)->put(route('target-roles.analysis.review.update', [$role, $analysis]), reviewedProposalPayload())->assertRedirect();
    $this->actingAs($user)->post(route('target-roles.confirm', $role))->assertSessionHasErrors('acknowledge_uncertainty');
    $this->actingAs($user)->post(route('target-roles.confirm', $role), ['acknowledge_uncertainty' => true])->assertRedirect(route('target-roles.show', $role));

    expect($role->refresh()->isActive())->toBeTrue()
        ->and($role->confirmed_at)->not->toBeNull()
        ->and($familiar->fresh()->target_role_id)->toBe($role->id)
        ->and($familiar->fresh()->stage)->toBe(FamiliarStage::Summoned)
        ->and(FamiliarProgressionEvent::query()->whereBelongsTo($familiar, 'familiar')->where('event_type', 'familiar_evolved')->count())->toBe(0)
        ->and(FamiliarQuestProgress::query()->whereBelongsTo($familiar, 'familiar')->count())->toBe(0);
});

test('stale and cross-user analyses cannot be applied or polled', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    [$role, $analysis] = reviewableAnalysis($owner);

    $this->actingAs($intruder)->get(route('target-roles.show', $role))->assertNotFound();
    $this->actingAs($intruder)
        ->put(route('target-roles.analysis.review.update', [$role, $analysis]), reviewedProposalPayload())
        ->assertNotFound();

    $role->update(['original_job_listing' => analysisReviewListing().' changed']);
    $this->actingAs($owner)
        ->put(route('target-roles.analysis.review.update', [$role, $analysis]), reviewedProposalPayload())
        ->assertSessionHasErrors('analysis');

    expect($role->responsibilities()->count())->toBe(0)
        ->and(RoleAnalysisReview::query()->count())->toBe(0);
});

test('a listing with no company stays explicitly unknown in review', function () {
    $user = User::factory()->create();
    [$role] = reviewableAnalysis($user, analysisReviewProposal(null));

    $this->actingAs($user)->get(route('target-roles.show', $role))->assertInertia(fn (Assert $page) => $page
        ->where('targetRole.company_name', null)
        ->where('analysis.proposal.company_name', null)
        ->missing('fabricated_company_data'));
});

test('review markup keeps evidence uncertainty polling and keyboard controls explicit', function () {
    $review = file_get_contents(resource_path('js/features/target-roles/components/job-listing-review-form.tsx'));
    $show = file_get_contents(resource_path('js/pages/target-roles/show.tsx'));

    expect($review)->not->toBeFalse()
        ->and($review)->toContain('Listing evidence:')
        ->and($review)->toContain('Extraction confidence:')
        ->and($review)->toContain('AI inferred')
        ->and($review)->toContain('Assumptions')
        ->and($review)->toContain('Clarification questions')
        ->and($review)->toContain('type="button"')
        ->and($review)->toContain('aria-label={`Move ${label}')
        ->and($review)->not->toContain('draggable')
        ->and($review)->not->toContain('readiness score')
        ->and($show)->not->toBeFalse()
        ->and($show)->toContain("mode: 'rest'")
        ->and($show)->toContain('visibilitychange')
        ->and($show)->toContain('Refresh status')
        ->and($show)->not->toContain('setInterval');
});
