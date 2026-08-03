<?php

use App\Actions\TargetRoles\SyncRoleCompetencies;
use App\Actions\TargetRoles\SyncRoleResponsibilities;
use App\Enums\CompetencyCategory;
use App\Enums\DataSource;
use App\Enums\Priority;
use App\Enums\RoleAnalysisStatus;
use App\Enums\Seniority;
use App\Enums\TargetRoleSourceType;
use App\Enums\TargetRoleStatus;
use App\Models\RoleAnalysis;
use App\Models\RoleCompetency;
use App\Models\RoleResponsibility;
use App\Models\TargetRole;
use App\Models\User;
use App\Policies\TargetRolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('target role domain tables contain the approved string columns and soft deletion', function () {
    expect(Schema::hasColumns('target_roles', [
        'public_id',
        'user_id',
        'status',
        'source_type',
        'title',
        'company_name',
        'seniority',
        'role_summary',
        'original_job_listing',
        'interview_at',
        'biggest_concern',
        'confirmed_at',
        'archived_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('role_responsibilities', [
            'target_role_id',
            'content',
            'priority',
            'source',
            'source_evidence',
            'sort_order',
            'user_confirmed_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('role_competencies', [
            'target_role_id',
            'name',
            'category',
            'priority',
            'source_evidence',
            'source',
            'user_confidence',
            'sort_order',
            'user_confirmed_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('role_analyses', [
            'public_id',
            'target_role_id',
            'status',
            'prompt_version',
            'provider',
            'model',
            'source_hash',
            'structured_result',
            'assumptions',
            'usage_metadata',
            'failure_category',
            'failure_summary',
            'started_at',
            'completed_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('target_roles', 'deleted_at'))->toBeTrue();
});

test('a user owns many target roles and children inherit ownership through their target role', function () {
    $user = User::factory()->create();
    $targetRole = TargetRole::factory()->for($user)->create();
    $responsibility = RoleResponsibility::factory()->for($targetRole)->create();
    $competency = RoleCompetency::factory()->for($targetRole)->create();
    $analysis = RoleAnalysis::factory()->for($targetRole)->create();

    expect($user->targetRoles)->toHaveCount(1)
        ->and($user->targetRoles->first()->is($targetRole))->toBeTrue()
        ->and($targetRole->user->is($user))->toBeTrue()
        ->and($responsibility->targetRole->user->is($user))->toBeTrue()
        ->and($competency->targetRole->user->is($user))->toBeTrue()
        ->and($analysis->targetRole->user->is($user))->toBeTrue()
        ->and(Schema::hasColumn('role_responsibilities', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumn('role_competencies', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumn('role_analyses', 'user_id'))->toBeFalse();
});

test('target role and child attributes cast to approved enums and dates', function () {
    $targetRole = TargetRole::factory()->confirmed()->create([
        'source_type' => TargetRoleSourceType::JobListing,
        'seniority' => Seniority::Senior,
        'interview_at' => now()->addWeek(),
    ]);
    $responsibility = RoleResponsibility::factory()->for($targetRole)->criticalPriority()->create([
        'source' => DataSource::JobListing,
        'user_confirmed_at' => now(),
    ]);
    $competency = RoleCompetency::factory()->for($targetRole)->leadership()->create([
        'source' => DataSource::UserCorrected,
        'priority' => Priority::High,
        'user_confirmed_at' => now(),
    ]);

    expect($targetRole->status)->toBe(TargetRoleStatus::Confirmed)
        ->and($targetRole->source_type)->toBe(TargetRoleSourceType::JobListing)
        ->and($targetRole->seniority)->toBe(Seniority::Senior)
        ->and($targetRole->interview_at)->not->toBeNull()
        ->and($targetRole->confirmed_at)->not->toBeNull()
        ->and($responsibility->priority)->toBe(Priority::Critical)
        ->and($responsibility->source)->toBe(DataSource::JobListing)
        ->and($responsibility->user_confirmed_at)->not->toBeNull()
        ->and($competency->category)->toBe(CompetencyCategory::Leadership)
        ->and($competency->priority)->toBe(Priority::High)
        ->and($competency->source)->toBe(DataSource::UserCorrected);
});

test('public IDs are ULID route keys and are immutable', function () {
    $targetRole = TargetRole::factory()->create();
    $analysis = RoleAnalysis::factory()->for($targetRole)->create();

    expect(Str::isUlid($targetRole->public_id))->toBeTrue()
        ->and(Str::isUlid($analysis->public_id))->toBeTrue()
        ->and($targetRole->getRouteKeyName())->toBe('public_id')
        ->and($analysis->getRouteKeyName())->toBe('public_id')
        ->and($targetRole->getRouteKey())->toBe($targetRole->public_id)
        ->and($analysis->getRouteKey())->toBe($analysis->public_id);

    expect(function () use ($targetRole): void {
        $targetRole->forceFill(['public_id' => (string) Str::ulid()])->save();
    })->toThrow(LogicException::class, 'A target role public ID is immutable.');

    expect(function () use ($analysis): void {
        $analysis->forceFill(['public_id' => (string) Str::ulid()])->save();
    })->toThrow(LogicException::class, 'A role analysis public ID is immutable.');
});

test('the original listing is encrypted at rest and hidden from serialization', function () {
    $listing = 'Distinct private listing text for a principal engineering role.';
    $targetRole = TargetRole::factory()->jobListingDraft()->create([
        'original_job_listing' => $listing,
    ]);

    expect($targetRole->original_job_listing)->toBe($listing)
        ->and($targetRole->getRawOriginal('original_job_listing'))->not->toBe($listing)
        ->and($targetRole->toArray())->not->toHaveKey('original_job_listing');
});

test('confirmed and archived state invariants are enforced', function () {
    $user = User::factory()->create();

    expect(fn () => TargetRole::factory()->for($user)->create([
        'status' => TargetRoleStatus::Confirmed,
        'confirmed_at' => null,
    ]))->toThrow(DomainException::class, 'A confirmed target role requires confirmed_at');

    expect(fn () => TargetRole::factory()->for($user)->create([
        'status' => TargetRoleStatus::Draft,
        'confirmed_at' => now(),
    ]))->toThrow(DomainException::class, 'An unconfirmed target role cannot carry confirmation');

    expect(fn () => TargetRole::factory()->for($user)->create([
        'status' => TargetRoleStatus::Archived,
        'confirmed_at' => now(),
        'archived_at' => null,
    ]))->toThrow(DomainException::class, 'An archived target role requires confirmation');
});

test('status helpers and scopes separate active confirmed and archived roles by owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $active = TargetRole::factory()->for($owner)->confirmed()->create();
    $archived = TargetRole::factory()->for($owner)->archived()->create();
    $draft = TargetRole::factory()->for($owner)->manualDraft()->create();
    TargetRole::factory()->for($otherUser)->confirmed()->create();

    expect($active->isConfirmed())->toBeTrue()
        ->and($active->isActive())->toBeTrue()
        ->and($active->isArchived())->toBeFalse()
        ->and($archived->isArchived())->toBeTrue()
        ->and($archived->isActive())->toBeFalse()
        ->and($draft->isConfirmed())->toBeFalse()
        ->and(TargetRole::query()->ownedBy($owner)->count())->toBe(3)
        ->and(TargetRole::query()->ownedBy($owner)->active()->pluck('id')->all())->toBe([$active->id])
        ->and(TargetRole::query()->ownedBy($owner)->confirmed()->pluck('id')->all())->toBe([$active->id])
        ->and(TargetRole::query()->ownedBy($owner)->archived()->pluck('id')->all())->toBe([$archived->id]);
});

test('ordered relationships always return responsibilities and competencies by sort order', function () {
    $targetRole = TargetRole::factory()->create();

    foreach ([2, 0, 1] as $sortOrder) {
        RoleResponsibility::factory()->for($targetRole)->create([
            'content' => "Responsibility {$sortOrder}",
            'sort_order' => $sortOrder,
        ]);
        RoleCompetency::factory()->for($targetRole)->create([
            'name' => "Competency {$sortOrder}",
            'sort_order' => $sortOrder,
        ]);
    }

    expect($targetRole->responsibilities()->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($targetRole->competencies()->pluck('sort_order')->all())->toBe([0, 1, 2]);
});

test('synchronization actions replace children with contiguous zero based ordering', function () {
    $targetRole = TargetRole::factory()->create();
    $staleResponsibility = RoleResponsibility::factory()->for($targetRole)->create();
    $staleCompetency = RoleCompetency::factory()->for($targetRole)->create();

    $responsibilities = app(SyncRoleResponsibilities::class)($targetRole, [
        8 => ['content' => 'First', 'priority' => Priority::High, 'source' => DataSource::User],
        3 => ['content' => 'Second', 'priority' => Priority::Medium, 'source' => DataSource::UserCorrected],
    ]);
    $competencies = app(SyncRoleCompetencies::class)($targetRole, [
        9 => ['name' => 'PHP', 'category' => CompetencyCategory::Technical, 'priority' => Priority::High, 'source' => DataSource::User],
        4 => ['name' => 'Mentoring', 'category' => CompetencyCategory::Leadership, 'priority' => Priority::Medium, 'source' => DataSource::UserCorrected],
        2 => ['name' => 'Delivery', 'category' => CompetencyCategory::Delivery, 'priority' => Priority::Low, 'source' => DataSource::User],
    ]);

    expect($responsibilities->pluck('sort_order')->all())->toBe([0, 1])
        ->and($responsibilities->pluck('content')->all())->toBe(['First', 'Second'])
        ->and($competencies->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($competencies->pluck('name')->all())->toBe(['PHP', 'Mentoring', 'Delivery'])
        ->and($responsibilities->every(fn (RoleResponsibility $responsibility): bool => $responsibility->target_role_id === $targetRole->id))->toBeTrue()
        ->and($competencies->every(fn (RoleCompetency $competency): bool => $competency->target_role_id === $targetRole->id))->toBeTrue();

    $this->assertModelMissing($staleResponsibility);
    $this->assertModelMissing($staleCompetency);
});

test('analysis JSON data and lifecycle scopes are cast and queryable', function () {
    $targetRole = TargetRole::factory()->jobListingDraft()->create();
    $pending = RoleAnalysis::factory()->for($targetRole)->pending()->create();
    $completed = RoleAnalysis::factory()->for($targetRole)->completed()->create();
    $failed = RoleAnalysis::factory()->for($targetRole)->failed()->create();

    expect($completed->structured_result)->toBeArray()
        ->and($completed->structured_result['normalized_title'])->toBe('Software Engineer')
        ->and($completed->assumptions)->toBe([])
        ->and($completed->usage_metadata)->toBe(['input_tokens' => 100, 'output_tokens' => 50])
        ->and($pending->isPending())->toBeTrue()
        ->and($completed->isCompleted())->toBeTrue()
        ->and($failed->isFailed())->toBeTrue()
        ->and($completed->isTerminal())->toBeTrue()
        ->and(RoleAnalysis::query()->pending()->pluck('id')->all())->toBe([$pending->id])
        ->and(RoleAnalysis::query()->completed()->pluck('id')->all())->toBe([$completed->id])
        ->and(RoleAnalysis::query()->failed()->pluck('id')->all())->toBe([$failed->id]);
});

test('analysis source hashes are deterministic and terminal analyses are immutable', function () {
    expect(RoleAnalysis::sourceHash("  A role\r\nwith details  "))
        ->toBe(RoleAnalysis::sourceHash("A role\nwith details"))
        ->and(RoleAnalysis::sourceHash('A different role'))
        ->not->toBe(RoleAnalysis::sourceHash('A role'));

    $completed = RoleAnalysis::factory()->completed()->create();

    expect(fn () => $completed->update(['failure_summary' => 'Changed later']))
        ->toThrow(LogicException::class, 'A terminal role analysis is immutable.');
});

test('target role factory lifecycle states produce valid records', function () {
    $manual = TargetRole::factory()->manualDraft()->create();
    $listing = TargetRole::factory()->jobListingDraft()->create();
    $pending = TargetRole::factory()->analysisPending()->create();
    $review = TargetRole::factory()->reviewRequired()->create();
    $confirmed = TargetRole::factory()->confirmed()->create();
    $archived = TargetRole::factory()->archived()->create();

    expect($manual->source_type)->toBe(TargetRoleSourceType::Manual)
        ->and($listing->source_type)->toBe(TargetRoleSourceType::JobListing)
        ->and($listing->original_job_listing)->not->toBeNull()
        ->and($pending->status)->toBe(TargetRoleStatus::AnalysisPending)
        ->and($review->status)->toBe(TargetRoleStatus::ReviewRequired)
        ->and($confirmed->isActive())->toBeTrue()
        ->and($archived->isArchived())->toBeTrue();
});

test('responsibility priority factory states are valid', function (Priority $priority, string $state) {
    $responsibility = RoleResponsibility::factory()->{$state}()->create();

    expect($responsibility->priority)->toBe($priority);
})->with([
    'low' => [Priority::Low, 'lowPriority'],
    'medium' => [Priority::Medium, 'mediumPriority'],
    'high' => [Priority::High, 'highPriority'],
    'critical' => [Priority::Critical, 'criticalPriority'],
]);

test('competency category factory states are valid', function (CompetencyCategory $category, string $state) {
    $competency = RoleCompetency::factory()->{$state}()->create();

    expect($competency->category)->toBe($category);
})->with([
    'technical' => [CompetencyCategory::Technical, 'technical'],
    'behavioral' => [CompetencyCategory::Behavioral, 'behavioral'],
    'leadership' => [CompetencyCategory::Leadership, 'leadership'],
    'domain' => [CompetencyCategory::Domain, 'domain'],
    'tooling' => [CompetencyCategory::Tooling, 'tooling'],
    'communication' => [CompetencyCategory::Communication, 'communication'],
    'delivery' => [CompetencyCategory::Delivery, 'delivery'],
]);

test('the target role policy grants owner abilities and denies cross user access', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $draft = TargetRole::factory()->for($owner)->jobListingDraft()->create();
    $confirmed = TargetRole::factory()->for($owner)->confirmed()->create();
    $archived = TargetRole::factory()->for($owner)->archived()->create();
    $policy = app(TargetRolePolicy::class);

    expect($policy->viewAny($owner))->toBeTrue()
        ->and($policy->create($owner))->toBeTrue()
        ->and($policy->view($owner, $draft))->toBeTrue()
        ->and($policy->update($owner, $draft))->toBeTrue()
        ->and($policy->confirm($owner, $draft))->toBeTrue()
        ->and($policy->analyze($owner, $draft))->toBeTrue()
        ->and($policy->archive($owner, $confirmed))->toBeTrue()
        ->and($policy->update($owner, $archived))->toBeFalse()
        ->and($policy->restore($owner, $archived))->toBeTrue()
        ->and($policy->delete($owner, $draft))->toBeTrue()
        ->and($policy->forceDelete($owner, $draft))->toBeFalse()
        ->and($policy->view($otherUser, $draft))->toBeFalse()
        ->and($policy->update($otherUser, $draft))->toBeFalse()
        ->and($policy->confirm($otherUser, $draft))->toBeFalse()
        ->and($policy->archive($otherUser, $confirmed))->toBeFalse()
        ->and($policy->analyze($otherUser, $draft))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('view', $draft))->toBeFalse()
        ->and(TargetRole::query()->ownedBy($otherUser)->whereKey($draft)->exists())->toBeFalse();
});

test('analysis policy excludes manual pending confirmed and archived roles', function () {
    $owner = User::factory()->create();
    $policy = app(TargetRolePolicy::class);
    $manual = TargetRole::factory()->for($owner)->manualDraft()->create();
    $pending = TargetRole::factory()->for($owner)->analysisPending()->create();
    $confirmed = TargetRole::factory()->for($owner)->confirmed()->create([
        'source_type' => TargetRoleSourceType::JobListing,
    ]);
    $archived = TargetRole::factory()->for($owner)->archived()->create([
        'source_type' => TargetRoleSourceType::JobListing,
    ]);

    expect($policy->analyze($owner, $manual))->toBeFalse()
        ->and($policy->analyze($owner, $pending))->toBeFalse()
        ->and($policy->analyze($owner, $confirmed))->toBeFalse()
        ->and($policy->analyze($owner, $archived))->toBeFalse();
});

test('TypeScript unions contain every PHP enum value', function (string $enumClass) {
    $types = file_get_contents(resource_path('js/types/target-roles.ts'));

    expect($types)->not->toBeFalse();

    foreach ($enumClass::cases() as $case) {
        expect($types)->toContain("'{$case->value}'");
    }
})->with([
    TargetRoleStatus::class,
    TargetRoleSourceType::class,
    Seniority::class,
    CompetencyCategory::class,
    Priority::class,
    DataSource::class,
    RoleAnalysisStatus::class,
]);
