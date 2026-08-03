<?php

namespace App\Actions\Familiars;

use App\Ai\Agents\RoleGroundedCoach;
use App\Ai\Exceptions\RoleGroundedCoachingException;
use App\Ai\RoleGroundedCoachingResponse;
use App\Enums\FamiliarInteractionIntent;
use App\Models\TargetRole;
use App\Models\User;
use App\Models\UserFamiliar;
use App\Support\Familiars\ConfirmedRoleContext;
use App\Support\Familiars\ConfirmedRoleTopic;
use App\Support\Familiars\ConfirmedRoleTopics;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class AskPrototypeInterviewGuide
{
    private const int MaximumAttempts = 2;

    public function __construct(
        private ConfirmedRoleContext $roleContext,
        private ConfirmedRoleTopics $roleTopics,
    ) {}

    public function __invoke(
        User $user,
        FamiliarInteractionIntent $intent,
        ?string $topicId = null,
        ?string $question = null,
    ): RoleGroundedCoachingResponse {
        $targetRole = $user->targetRoles()
            ->active()
            ->with(['familiar', 'responsibilities', 'competencies'])
            ->first();

        if ($targetRole === null) {
            throw new RoleGroundedCoachingException('active_role_required');
        }

        $familiar = $targetRole->familiar;

        if (! $familiar instanceof UserFamiliar || $familiar->user_id !== $user->id) {
            throw new RoleGroundedCoachingException('active_role_familiar_required');
        }

        $this->ensureConfigured();
        $context = $this->roleContext->for($targetRole);
        $topic = $this->resolveTopic($targetRole, $context, $intent, $topicId);

        if ($intent === FamiliarInteractionIntent::CustomQuestion && $question === null) {
            throw new RoleGroundedCoachingException('custom_question_required');
        }

        $prompt = json_encode([
            'confirmed_role_context' => $context,
            'selected_topic' => $topic?->promptContext(),
            'custom_question' => $intent === FamiliarInteractionIntent::CustomQuestion ? $question : null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        for ($attempt = 1; $attempt <= self::MaximumAttempts; $attempt++) {
            $attemptStartedAt = hrtime(true);

            try {
                $response = (new RoleGroundedCoach($intent))->prompt($prompt);

                return $this->validatedResponse($response, $intent, $topic);
            } catch (RoleGroundedCoachingException $exception) {
                if ($attempt < self::MaximumAttempts && $this->isRetryableResponseFailure($exception)) {
                    continue;
                }

                throw $this->withAttemptContext($exception, $attempt, $intent, $prompt, $attemptStartedAt);
            } catch (ProviderOverloadedException $exception) {
                if ($attempt < self::MaximumAttempts) {
                    continue;
                }

                throw $this->providerException('provider_overload', $exception, $attempt, $intent, $prompt, $attemptStartedAt);
            } catch (RequestException $exception) {
                if ($attempt < self::MaximumAttempts && $exception->response->serverError()) {
                    continue;
                }

                throw $this->providerException('provider_failure', $exception, $attempt, $intent, $prompt, $attemptStartedAt);
            } catch (RateLimitedException $exception) {
                throw $this->providerException('provider_rate_limit', $exception, $attempt, $intent, $prompt, $attemptStartedAt);
            } catch (InsufficientCreditsException $exception) {
                throw $this->providerException('insufficient_credits', $exception, $attempt, $intent, $prompt, $attemptStartedAt);
            } catch (ConnectionException $exception) {
                $reason = str_contains(mb_strtolower($exception->getMessage()), 'timed out')
                    ? 'provider_timeout'
                    : 'network_failure';

                throw $this->providerException($reason, $exception, $attempt, $intent, $prompt, $attemptStartedAt);
            } catch (Throwable $exception) {
                throw $this->providerException('provider_failure', $exception, $attempt, $intent, $prompt, $attemptStartedAt);
            }
        }

        throw new RoleGroundedCoachingException('provider_failure');
    }

    private function ensureConfigured(): void
    {
        if (blank(config('ai.providers.openai.key')) || blank(config('ai.providers.openai.models.text.default'))) {
            throw new RoleGroundedCoachingException('missing_configuration');
        }
    }

    /** @param array<string, mixed> $context */
    private function resolveTopic(
        TargetRole $targetRole,
        array $context,
        FamiliarInteractionIntent $intent,
        ?string $topicId,
    ): ?ConfirmedRoleTopic {
        if ($topicId !== null) {
            $topic = $this->roleTopics->resolve($targetRole, $context, $topicId);

            if ($topic === null) {
                throw new RoleGroundedCoachingException('topic_unavailable');
            }

            return $topic;
        }

        if (! in_array($intent, [
            FamiliarInteractionIntent::Teach,
            FamiliarInteractionIntent::AskQuestion,
            FamiliarInteractionIntent::PracticeTopic,
        ], true)) {
            return null;
        }

        $topic = $this->roleTopics->select($targetRole, $context);

        if ($intent === FamiliarInteractionIntent::PracticeTopic && $topic === null) {
            throw new RoleGroundedCoachingException('topic_unavailable');
        }

        return $topic;
    }

    private function validatedResponse(
        AgentResponse $response,
        FamiliarInteractionIntent $intent,
        ?ConfirmedRoleTopic $topic,
    ): RoleGroundedCoachingResponse {
        $finishReason = $response->steps->last()->finishReason;

        if ($finishReason === FinishReason::Length) {
            throw new RoleGroundedCoachingException('output_truncation', [
                ...$this->responseContext($response),
            ]);
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new RoleGroundedCoachingException('unstructured_response', $this->responseContext($response));
        }

        if (! json_validate($response->text)) {
            throw new RoleGroundedCoachingException('invalid_json', $this->responseContext($response));
        }

        try {
            return RoleGroundedCoachingResponse::fromStructured($response->toArray(), $intent, $topic);
        } catch (RoleGroundedCoachingException $exception) {
            throw new RoleGroundedCoachingException($exception->reason, [
                ...$exception->context,
                ...$this->responseContext($response),
            ], $exception);
        }
    }

    private function isRetryableResponseFailure(RoleGroundedCoachingException $exception): bool
    {
        return in_array($exception->reason, [
            'invalid_json',
            'unstructured_response',
            'output_truncation',
            'schema_rejection',
            'missing_required_field',
            'string_length_violation',
        ], true);
    }

    private function withAttemptContext(
        RoleGroundedCoachingException $exception,
        int $attempt,
        FamiliarInteractionIntent $intent,
        string $prompt,
        int $attemptStartedAt,
    ): RoleGroundedCoachingException {
        return new RoleGroundedCoachingException($exception->reason, [
            ...$exception->context,
            ...$this->operationalContext($attempt, $intent, $prompt, $attemptStartedAt),
        ], $exception);
    }

    private function providerException(
        string $reason,
        Throwable $exception,
        int $attempt,
        FamiliarInteractionIntent $intent,
        string $prompt,
        int $attemptStartedAt,
    ): RoleGroundedCoachingException {
        return new RoleGroundedCoachingException(
            $reason,
            $this->operationalContext($attempt, $intent, $prompt, $attemptStartedAt),
            $exception,
        );
    }

    /** @return array{attempts: int, duration_ms: int, intent: string, model: string, prompt_length: int, provider: string, schema_version: string} */
    private function operationalContext(
        int $attempt,
        FamiliarInteractionIntent $intent,
        string $prompt,
        int $attemptStartedAt,
    ): array {
        return [
            'attempts' => $attempt,
            'duration_ms' => (int) ((hrtime(true) - $attemptStartedAt) / 1_000_000),
            'intent' => $intent->value,
            'model' => (string) config('ai.providers.openai.models.text.default'),
            'prompt_length' => mb_strlen($prompt),
            'provider' => 'openai',
            'schema_version' => 'familiar-guided-v1',
        ];
    }

    /** @return array{completion_tokens: int, finish_reason: string, model: string, prompt_tokens: int, provider: string, reasoning_tokens: int, response_length: int} */
    private function responseContext(AgentResponse $response): array
    {
        return [
            'completion_tokens' => $response->usage->completionTokens,
            'finish_reason' => $response->steps->last()?->finishReason->value ?? 'unknown',
            'model' => $response->meta->model ?? 'unknown',
            'prompt_tokens' => $response->usage->promptTokens,
            'provider' => $response->meta->provider ?? 'unknown',
            'reasoning_tokens' => $response->usage->reasoningTokens,
            'response_length' => mb_strlen($response->text),
        ];
    }
}
