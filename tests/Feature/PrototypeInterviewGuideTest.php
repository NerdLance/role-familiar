<?php

use App\Actions\Familiars\AskPrototypeInterviewGuide;
use App\Ai\Agents\PrototypeInterviewGuide;
use App\Ai\Exceptions\PrototypeInterviewGuideException;
use App\Enums\FamiliarReaction;
use App\Enums\PrototypeQuestKey;
use App\Models\User;
use App\Models\UserFamiliar;
use Illuminate\Support\Facades\Log;
use RuntimeException;

beforeEach(function (): void {
    config([
        'ai.providers.openai.key' => 'test-key',
        'ai.providers.openai.models.text.default' => 'test-model',
    ]);

    PrototypeInterviewGuide::fake()->preventStrayPrompts();
});

test('guests cannot ask the prototype interview guide', function () {
    $this->post(route('familiar.interview-guide'), ['message' => 'How should I prepare?'])
        ->assertRedirect(route('login'));

    PrototypeInterviewGuide::assertNeverPrompted();
});

test('the guide request validates a short message', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('familiar'))
        ->post(route('familiar.interview-guide'), ['message' => ''])
        ->assertRedirect(route('familiar'))
        ->assertSessionHasErrors('message');

    PrototypeInterviewGuide::assertNeverPrompted();
});

test('the guide receives only the submitted question and returns controlled structured data', function () {
    PrototypeInterviewGuide::fake([[
        'message' => 'Name the situation, your actions, and the result.',
        'reaction' => FamiliarReaction::Encouraging->value,
        'suggested_quest' => PrototypeQuestKey::StarResponse->value,
    ]]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('familiar.interview-guide'), ['message' => 'How do I use STAR?'])
        ->assertOk()
        ->assertExactJson([
            'message' => 'Name the situation, your actions, and the result.',
            'reaction' => 'encouraging',
            'suggested_quest' => 'star_response',
        ]);

    PrototypeInterviewGuide::assertPrompted('How do I use STAR?');
});

test('each allowed reaction is accepted', function (FamiliarReaction $reaction) {
    PrototypeInterviewGuide::fake([[
        'message' => 'A concise guide response.',
        'reaction' => $reaction->value,
        'suggested_quest' => null,
    ]]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('familiar.interview-guide'), ['message' => 'How should I practice?'])
        ->assertOk()
        ->assertJsonPath('reaction', $reaction->value);
})->with(FamiliarReaction::cases());

test('an allowed quest suggestion remains a response value only', function () {
    PrototypeInterviewGuide::fake([[
        'message' => 'Try outlining one concise example.',
        'reaction' => FamiliarReaction::Neutral->value,
        'suggested_quest' => PrototypeQuestKey::BehavioralStory->value,
    ]]);
    $user = User::factory()->create();
    $familiar = UserFamiliar::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('familiar.interview-guide'), ['message' => 'What should I practice?'])
        ->assertOk();

    expect($familiar->fresh()->stage->value)->toBe('summoned')
        ->and($familiar->questProgress()->count())->toBe(0);
});

test('invalid structured output is handled without exposing provider data', function () {
    Log::spy();

    PrototypeInterviewGuide::fake([[
        'message' => 'This should not reach the browser.',
        'reaction' => 'invented_reaction',
        'suggested_quest' => null,
    ]]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('familiar.interview-guide'), ['message' => 'A private interview detail'])
        ->assertServiceUnavailable();

    expect($response->json('message'))
        ->toStartWith('Queue Wyrm needs a moment. Please try again soon. Reference:')
        ->not->toContain('A private interview detail');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $event, array $context): bool {
            return $event === 'Prototype interview guide request failed.'
                && $context['category'] === 'prototype_response'
                && $context['reason'] === 'invalid_reaction'
                && is_string($context['reference'])
                && $context['message_length'] === 26
                && ! array_key_exists('message', $context);
        });
});

test('missing model configuration prevents an external prompt', function () {
    config(['ai.providers.openai.models.text.default' => null]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('familiar.interview-guide'), ['message' => 'How do I prepare?'])
        ->assertServiceUnavailable();

    expect($response->json('message'))->toStartWith('Queue Wyrm needs a moment. Please try again soon. Reference:');

    PrototypeInterviewGuide::assertNeverPrompted();
});

test('provider failures return a controlled response', function () {
    PrototypeInterviewGuide::fake([
        fn (): never => throw new RuntimeException('provider failure'),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('familiar.interview-guide'), ['message' => 'How should I prepare?'])
        ->assertServiceUnavailable();

    expect($response->json('message'))->toStartWith('Queue Wyrm is unavailable right now. Please try again soon. Reference:');
});

test('the guide endpoint is throttled for authenticated users', function () {
    PrototypeInterviewGuide::fake([[
        'message' => 'A concise guide response.',
        'reaction' => FamiliarReaction::Neutral->value,
        'suggested_quest' => null,
    ]]);
    $user = User::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)
            ->postJson(route('familiar.interview-guide'), ['message' => "Question {$attempt}"])
            ->assertOk();
    }

    $this->actingAs($user)
        ->postJson(route('familiar.interview-guide'), ['message' => 'One more question'])
        ->assertTooManyRequests();
});

test('the response mapper rejects arbitrary quest keys', function () {
    PrototypeInterviewGuide::fake([[
        'message' => 'A concise guide response.',
        'reaction' => FamiliarReaction::Neutral->value,
        'suggested_quest' => 'invented_quest',
    ]]);

    expect(fn () => app(AskPrototypeInterviewGuide::class)('Question'))
        ->toThrow(PrototypeInterviewGuideException::class);
});
