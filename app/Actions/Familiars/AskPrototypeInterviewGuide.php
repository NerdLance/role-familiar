<?php

namespace App\Actions\Familiars;

use App\Ai\Agents\PrototypeInterviewGuide;
use App\Ai\Exceptions\PrototypeInterviewGuideException;
use App\Ai\PrototypeInterviewGuideResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;

class AskPrototypeInterviewGuide
{
    public function __construct(private PrototypeInterviewGuide $guide) {}

    public function __invoke(string $message): PrototypeInterviewGuideResponse
    {
        $this->ensureConfigured();

        $response = $this->guide->prompt($message);

        if (! $response instanceof StructuredAgentResponse) {
            throw new PrototypeInterviewGuideException('The guide did not return structured output.');
        }

        return PrototypeInterviewGuideResponse::fromStructured($response->toArray());
    }

    private function ensureConfigured(): void
    {
        if (blank(config('ai.providers.openai.key')) || blank(config('ai.providers.openai.models.text.default'))) {
            throw new PrototypeInterviewGuideException('The prototype interview guide is unavailable.');
        }
    }
}
