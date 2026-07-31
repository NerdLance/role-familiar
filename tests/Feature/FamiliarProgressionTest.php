<?php

use App\Actions\Familiars\CompletePrototypeQuest;
use App\Actions\Familiars\EvolvePrototypeFamiliar;
use App\Actions\Familiars\SummonPrototypeFamiliar;
use App\Enums\FamiliarStage;
use App\Enums\ProgressionEventType;
use App\Enums\PrototypeQuestKey;
use App\Models\FamiliarQuestProgress;
use App\Models\User;
use App\Models\UserFamiliar;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;

test('guests cannot access the persistent familiar page', function () {
    $this->get(route('familiar'))
        ->assertRedirect(route('login'));
});

test('summoning creates one default queue wyrm with a public route key and event', function () {
    $user = User::factory()->create();
    $summon = app(SummonPrototypeFamiliar::class);

    $familiar = $summon($user);

    expect($familiar->species_key)->toBe('queue_wyrm')
        ->and($familiar->name)->toBe('Queue Wyrm')
        ->and($familiar->stage)->toBe(FamiliarStage::Summoned)
        ->and($familiar->summoned_at)->not->toBeNull()
        ->and($familiar->public_id)->not->toBeEmpty()
        ->and($familiar->getRouteKeyName())->toBe('public_id')
        ->and($familiar->progressionEvents()
            ->where('event_type', ProgressionEventType::FamiliarSummoned)
            ->count())->toBe(1)
        ->and($summon($user)->id)->toBe($familiar->id);

    expect(UserFamiliar::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('quest completion is idempotent and creates no duplicate progress or events', function () {
    $familiar = app(SummonPrototypeFamiliar::class)(User::factory()->create());
    $complete = app(CompletePrototypeQuest::class);

    $complete($familiar, PrototypeQuestKey::BehavioralStory);
    $complete($familiar, PrototypeQuestKey::BehavioralStory);

    expect($familiar->fresh()->stage)->toBe(FamiliarStage::Summoned)
        ->and($familiar->questProgress()->count())->toBe(1)
        ->and($familiar->progressionEvents()
            ->where('event_type', ProgressionEventType::QuestCompleted)
            ->count())->toBe(1)
        ->and($familiar->progressionEvents()
            ->where('event_type', ProgressionEventType::FamiliarEvolved)
            ->count())->toBe(0);
});

test('two distinct completed quests evolve the familiar to attuned once', function () {
    $familiar = app(SummonPrototypeFamiliar::class)(User::factory()->create());
    $complete = app(CompletePrototypeQuest::class);

    $complete($familiar, PrototypeQuestKey::BehavioralStory);
    $complete($familiar, PrototypeQuestKey::StarResponse);
    $attunedAt = $familiar->fresh()->attuned_at;
    $complete($familiar, PrototypeQuestKey::StarResponse);

    expect($familiar->fresh()->stage)->toBe(FamiliarStage::Attuned)
        ->and($attunedAt)->not->toBeNull()
        ->and($familiar->fresh()->attuned_at->equalTo($attunedAt))->toBeTrue()
        ->and($familiar->progressionEvents()
            ->where('event_type', ProgressionEventType::FamiliarEvolved)
            ->count())->toBe(1);
});

test('all four quests evolve the familiar to interview ready without skipping a stage', function () {
    $familiar = app(SummonPrototypeFamiliar::class)(User::factory()->create());
    $complete = app(CompletePrototypeQuest::class);

    foreach (PrototypeQuestKey::cases() as $quest) {
        $complete($familiar, $quest);
    }

    expect($familiar->fresh()->stage)->toBe(FamiliarStage::InterviewReady)
        ->and($familiar->fresh()->attuned_at)->not->toBeNull()
        ->and($familiar->fresh()->interview_ready_at)->not->toBeNull()
        ->and($familiar->progressionEvents()
            ->where('event_type', ProgressionEventType::FamiliarEvolved)
            ->count())->toBe(2);
});

test('evolution advances one server-authoritative stage at a time and never downgrades', function () {
    $familiar = UserFamiliar::factory()->summoned()->create();
    FamiliarQuestProgress::factory()
        ->for($familiar, 'familiar')
        ->createMany(collect(PrototypeQuestKey::cases())->map(
            fn (PrototypeQuestKey $quest) => ['quest_key' => $quest],
        )->all());
    $evolve = app(EvolvePrototypeFamiliar::class);

    $evolve($familiar);
    expect($familiar->fresh()->stage)->toBe(FamiliarStage::Attuned);

    $evolve($familiar->fresh());
    expect($familiar->fresh()->stage)->toBe(FamiliarStage::InterviewReady);

    $interviewReadyAt = $familiar->fresh()->interview_ready_at;
    $evolve($familiar->fresh());

    expect($familiar->fresh()->stage)->toBe(FamiliarStage::InterviewReady)
        ->and($familiar->fresh()->interview_ready_at->equalTo($interviewReadyAt))->toBeTrue();
});

test('a failed evolution rolls back the quest progress and its event', function () {
    $familiar = app(SummonPrototypeFamiliar::class)(User::factory()->create());
    $evolve = Mockery::mock(EvolvePrototypeFamiliar::class);
    $evolve->shouldReceive('__invoke')->once()->andThrow(new RuntimeException('stop'));
    $complete = new CompletePrototypeQuest($evolve);

    expect(fn () => $complete($familiar, PrototypeQuestKey::BehavioralStory))
        ->toThrow(RuntimeException::class, 'stop');

    expect($familiar->questProgress()->count())->toBe(0)
        ->and($familiar->progressionEvents()
            ->where('event_type', ProgressionEventType::QuestCompleted)
            ->count())->toBe(0);
});

test('familiar quest writes are owner protected and invalid quests receive validation errors', function () {
    $owner = User::factory()->create();
    $familiar = app(SummonPrototypeFamiliar::class)($owner);

    $this->actingAs(User::factory()->create())
        ->post(route('familiar.quests.complete', $familiar), [
            'quest' => PrototypeQuestKey::BehavioralStory->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->from(route('familiar'))
        ->post(route('familiar.quests.complete', $familiar), ['quest' => 'skip_stage'])
        ->assertRedirect(route('familiar'))
        ->assertSessionHasErrors('quest');

    expect($familiar->questProgress()->count())->toBe(0);
});

test('the persistent familiar page exposes controlled inertia props without local paths', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('familiar'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('familiar')
        ->where('familiar.species_key', 'queue_wyrm')
        ->where('familiar.stage', FamiliarStage::Summoned->value)
        ->has('manifest.stages', 3)
        ->has('manifest.evolutions', 2)
        ->has('manifest.overlays', 2)
        ->missing('familiar.user_id')
        ->missing('familiar.id')
    );

    expect($response->getContent())->not->toContain(base_path())
        ->and($response->getContent())->not->toContain('prototype-assets');
});
