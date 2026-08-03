<?php

namespace App\Ai\Agents;

use App\Enums\FamiliarReaction;
use App\Enums\PrototypeQuestKey;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
#[MaxTokens(300)]
#[Temperature(0.4)]
#[Timeout(20)]
class PrototypeInterviewGuide implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are Queue Wyrm, a supportive interview-preparation guide for a short prototype interaction.

Give concise, general interview-preparation guidance. Ask a clarifying question when essential context is missing. Never invent the user's experience or claim knowledge of an employer that was not provided. Do not offer legal, medical, or employment guarantees. Do not score readiness, create permanent quests, modify records, or call tools.

Return only the requested structured response. `suggested_quest` is optional and may only be one of the provided keys when it is genuinely useful; it must not imply completion.
INSTRUCTIONS;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->min(1)->max(1200)->required(),
            'reaction' => $schema->string()->enum(FamiliarReaction::class)->required(),
            'suggested_quest' => $schema->string()->enum(PrototypeQuestKey::class)->nullable(),
        ];
    }
}
