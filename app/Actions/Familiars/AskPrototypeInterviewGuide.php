<?php

namespace App\Actions\Familiars;

use App\Ai\Agents\RoleGroundedCoach;
use App\Ai\Exceptions\RoleGroundedCoachingException;
use App\Ai\RoleGroundedCoachingResponse;
use App\Models\User;
use App\Models\UserFamiliar;
use App\Support\Familiars\ConfirmedRoleContext;
use Laravel\Ai\Responses\StructuredAgentResponse;

class AskPrototypeInterviewGuide
{
    public function __construct(private ConfirmedRoleContext $roleContext) {}

    public function __invoke(User $user, string $message): RoleGroundedCoachingResponse
    {
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

        $prompt = json_encode([
            'confirmed_role_context' => $this->roleContext->for($targetRole),
            'question' => $message,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response = (new RoleGroundedCoach)->prompt($prompt);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RoleGroundedCoachingException('unstructured_response');
        }

        return RoleGroundedCoachingResponse::fromStructured($response->toArray());
    }

    private function ensureConfigured(): void
    {
        if (blank(config('ai.providers.openai.key')) || blank(config('ai.providers.openai.models.text.default'))) {
            throw new RoleGroundedCoachingException('missing_configuration');
        }
    }
}
