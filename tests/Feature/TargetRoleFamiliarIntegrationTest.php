<?php

use App\Actions\Familiars\AssignQueueWyrmToTargetRole;
use App\Actions\Familiars\CompletePrototypeQuest;
use App\Actions\TargetRoles\ConfirmTargetRole;
use App\Ai\Agents\JobListingAnalyst;
use App\Enums\FamiliarStage;
use App\Enums\ProgressionEventType;
use App\Enums\PrototypeQuestKey;
use App\Models\FamiliarProgressionEvent;
use App\Models\FamiliarQuestProgress;
use App\Models\RoleAnalysis;
use App\Models\RoleAnalysisReview;
use App\Models\RoleCompetency;
use App\Models\RoleResponsibility;
use App\Models\TargetRole;
use App\Models\User;
use App\Models\UserFamiliar;
use App\Support\Familiars\ConfirmedRoleContext;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Testing\AssertableInertia as Assert;

function confirmableFamiliarRole(User $user, array $attributes = []): TargetRole
{
    $targetRole = TargetRole::factory()->for($user)->manualDraft()->create([
        'title' => 'Senior Backend Engineer',
        'company_name' => 'Example Company',
        'role_summary' => 'Lead reliable backend systems.',
        ...$attributes,
    ]);
    RoleResponsibility::factory()->for($targetRole)->create([
        'content' => 'Design backend architecture',
        'sort_order' => 0,
    ]);
    RoleCompetency::factory()->for($targetRole)->create([
        'name' => 'API design',
        'category' => 'technical',
        'sort_order' => 0,
    ]);

    return $targetRole;
}

test('confirmation assigns the existing unbound Queue Wyrm and preserves progression', function () {
    JobListingAnalyst::fake()->preventStrayPrompts();
    $user = User::factory()->create();
    $targetRole = confirmableFamiliarRole($user);
    $familiar = UserFamiliar::factory()->for($user)->attuned()->create();
    FamiliarQuestProgress::factory()->for($familiar, 'familiar')->create([
        'quest_key' => PrototypeQuestKey::BehavioralStory,
    ]);

    $this->actingAs($user)->post(route('target-roles.confirm', $targetRole))->assertRedirect();

    $assignmentEvent = $familiar->progressionEvents()
        ->where('event_type', ProgressionEventType::TargetRoleAssigned)
        ->sole();

    expect($targetRole->refresh()->familiar->is($familiar))->toBeTrue()
        ->and($familiar->fresh()->target_role_id)->toBe($targetRole->id)
        ->and($familiar->fresh()->stage)->toBe(FamiliarStage::Attuned)
        ->and($familiar->questProgress()->count())->toBe(1)
        ->and($user->familiars()->count())->toBe(1)
        ->and($assignmentEvent->metadata)->toBe([
            'species_key' => 'queue_wyrm',
            'target_role_public_id' => $targetRole->public_id,
        ])
        ->and(json_encode($assignmentEvent->metadata))->not->toContain('backend architecture');
});

test('reconfirming a role preserves its familiar identity stage and event history', function () {
    $user = User::factory()->create();
    $targetRole = confirmableFamiliarRole($user);
    app(ConfirmTargetRole::class)($targetRole);
    $familiar = $targetRole->refresh()->familiar;
    $familiar->update(['stage' => FamiliarStage::Attuned, 'attuned_at' => now()]);
    $eventCount = $familiar->progressionEvents()->count();

    app(ConfirmTargetRole::class)($targetRole->refresh());

    expect($targetRole->refresh()->familiar->is($familiar))->toBeTrue()
        ->and($familiar->fresh()->stage)->toBe(FamiliarStage::Attuned)
        ->and($familiar->progressionEvents()->count())->toBe($eventCount);
});

test('later roles receive separate Queue Wyrms and progression never crosses roles', function () {
    $user = User::factory()->create();
    $firstRole = confirmableFamiliarRole($user, ['title' => 'Backend Engineer']);
    app(ConfirmTargetRole::class)($firstRole);
    $firstFamiliar = $firstRole->refresh()->familiar;
    app(CompletePrototypeQuest::class)($firstFamiliar, PrototypeQuestKey::BehavioralStory);

    $secondRole = confirmableFamiliarRole($user, ['title' => 'Engineering Manager']);
    app(ConfirmTargetRole::class)($secondRole);
    $secondFamiliar = $secondRole->refresh()->familiar;
    app(CompletePrototypeQuest::class)($secondFamiliar, PrototypeQuestKey::TechnicalReview);

    expect($firstRole->refresh()->isArchived())->toBeTrue()
        ->and($firstRole->familiar->is($firstFamiliar))->toBeTrue()
        ->and($secondRole->refresh()->isActive())->toBeTrue()
        ->and($secondFamiliar->isNot($firstFamiliar))->toBeTrue()
        ->and($secondFamiliar->stage)->toBe(FamiliarStage::Summoned)
        ->and($firstFamiliar->questProgress()->pluck('quest_key')->all())->toBe([PrototypeQuestKey::BehavioralStory])
        ->and($secondFamiliar->questProgress()->pluck('quest_key')->all())->toBe([PrototypeQuestKey::TechnicalReview]);
});

test('confirmed role context uses reviewed domain data and excludes raw analysis and assumptions', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->confirmed()->create([
        'title' => 'Senior Backend Engineer',
        'company_name' => 'Corrected Company',
        'role_summary' => 'Confirmed user summary.',
    ]);
    RoleResponsibility::factory()->for($targetRole)->create([
        'content' => 'Confirmed responsibility',
        'source_evidence' => 'PRIVATE RAW EVIDENCE',
        'user_confirmed_at' => now(),
        'sort_order' => 0,
    ]);
    RoleResponsibility::factory()->for($targetRole)->create([
        'content' => 'Unconfirmed responsibility',
        'user_confirmed_at' => null,
        'sort_order' => 1,
    ]);
    RoleCompetency::factory()->for($targetRole)->create([
        'name' => 'API architecture',
        'category' => 'technical',
        'user_confirmed_at' => now(),
        'sort_order' => 0,
    ]);
    RoleCompetency::factory()->for($targetRole)->create([
        'name' => 'Laravel',
        'category' => 'tooling',
        'user_confirmed_at' => now(),
        'sort_order' => 1,
    ]);
    $analysis = RoleAnalysis::factory()->for($targetRole)->completed()->create([
        'structured_result' => ['raw_secret' => 'PRIVATE RAW ANALYSIS'],
    ]);
    RoleAnalysisReview::factory()->for($analysis, 'roleAnalysis')->create([
        'reviewed_payload' => [
            'interview_focus_areas' => [[
                'name' => 'System design',
                'reason' => 'Architecture ownership is confirmed.',
            ]],
            'clarifications' => [
                ['question' => 'Direct reports?', 'reason' => 'Management is unclear.', 'status' => 'unresolved', 'answer' => null],
                ['question' => 'On-call?', 'reason' => 'Schedule was unclear.', 'status' => 'answered', 'answer' => 'Yes'],
            ],
            'assumptions' => [['statement' => 'PRIVATE UNRESOLVED ASSUMPTION']],
        ],
    ]);

    $contextService = app(ConfirmedRoleContext::class);
    $context = $contextService->for($targetRole);
    $serializedContext = json_encode($context, JSON_THROW_ON_ERROR);

    expect($context['role_title'])->toBe('Senior Backend Engineer')
        ->and($context['company'])->toBe('Corrected Company')
        ->and($context['responsibilities'])->toBe([[
            'content' => 'Confirmed responsibility',
            'priority' => 'medium',
        ]])
        ->and($context['competencies'][0]['name'])->toBe('API architecture')
        ->and($context['technologies'])->toBe([['name' => 'Laravel', 'priority' => 'medium']])
        ->and($context['interview_focus_areas'][0]['name'])->toBe('System design')
        ->and($context['unresolved_ambiguities'])->toBe([[
            'question' => 'Direct reports?',
            'reason' => 'Management is unclear.',
        ]])
        ->and($contextService->acknowledgement($context))->toBe('I have mapped the role. We will prepare for API architecture and Laravel.')
        ->and($serializedContext)->not->toContain('PRIVATE RAW ANALYSIS')
        ->and($serializedContext)->not->toContain('PRIVATE RAW EVIDENCE')
        ->and($serializedContext)->not->toContain('PRIVATE UNRESOLVED ASSUMPTION')
        ->and($serializedContext)->not->toContain('Unconfirmed responsibility');
});

test('cross-user familiar assignment is rejected', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $targetRole = TargetRole::factory()->for($owner)->confirmed()->create();

    expect(fn () => app(AssignQueueWyrmToTargetRole::class)($intruder, $targetRole))
        ->toThrow(AuthorizationException::class);

    expect($targetRole->familiar()->exists())->toBeFalse();
});

test('archiving removes the role from active familiar context without detaching history', function () {
    $user = User::factory()->create();
    $targetRole = confirmableFamiliarRole($user);
    app(ConfirmTargetRole::class)($targetRole);
    $familiar = $targetRole->refresh()->familiar;

    $this->actingAs($user)->post(route('target-roles.archive', $targetRole))->assertRedirect();

    expect($targetRole->refresh()->isArchived())->toBeTrue()
        ->and($familiar->fresh()->target_role_id)->toBe($targetRole->id);

    $this->actingAs($user)->get(route('familiar'))->assertInertia(fn (Assert $page) => $page
        ->component('familiar')
        ->where('familiar', null)
        ->where('targetRole', null));

    $this->actingAs($user)->post(route('familiar.quests.complete', $familiar), [
        'quest' => PrototypeQuestKey::TechnicalReview->value,
    ])->assertForbidden();
});

test('familiar page exposes only controlled confirmed role props', function () {
    $user = User::factory()->create();
    $targetRole = confirmableFamiliarRole($user, [
        'original_job_listing' => 'PRIVATE LISTING',
        'interview_at' => now()->addWeek(),
    ]);
    app(ConfirmTargetRole::class)($targetRole);

    $response = $this->actingAs($user)->get(route('familiar'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('familiar')
        ->where('familiar.species_key', 'queue_wyrm')
        ->where('familiar.stage', 'summoned')
        ->where('targetRole.public_id', $targetRole->public_id)
        ->where('targetRole.title', 'Senior Backend Engineer')
        ->where('targetRole.company_name', 'Example Company')
        ->has('targetRole.interview_at')
        ->has('targetRole.context.responsibilities', 1)
        ->has('targetRole.context.competencies', 1)
        ->has('targetRole.acknowledgement')
        ->missing('targetRole.user_id')
        ->missing('targetRole.original_job_listing')
        ->missing('targetRole.context.source_evidence')
        ->missing('targetRole.context.assumptions')
        ->missing('targetRole.context.provider')
        ->missing('targetRole.context.model'));

    expect($response->getContent())->not->toContain('PRIVATE LISTING');
});

test('confirmation summons only Stage 1 and does not evolve the Queue Wyrm', function () {
    JobListingAnalyst::fake()->preventStrayPrompts();
    $user = User::factory()->create();
    $targetRole = confirmableFamiliarRole($user);

    app(ConfirmTargetRole::class)($targetRole);
    $familiar = $targetRole->refresh()->familiar;

    expect($familiar->stage)->toBe(FamiliarStage::Summoned)
        ->and($familiar->questProgress()->count())->toBe(0)
        ->and(FamiliarProgressionEvent::query()
            ->whereBelongsTo($familiar, 'familiar')
            ->where('event_type', ProgressionEventType::FamiliarEvolved)
            ->exists())->toBeFalse();
});
