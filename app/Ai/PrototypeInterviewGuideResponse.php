<?php

namespace App\Ai;

use App\Ai\Exceptions\PrototypeInterviewGuideException;
use App\Enums\FamiliarReaction;
use App\Enums\PrototypeQuestKey;

readonly class PrototypeInterviewGuideResponse
{
    public function __construct(
        public string $message,
        public FamiliarReaction $reaction,
        public ?PrototypeQuestKey $suggestedQuest,
    ) {}

    /** @param array<string, mixed> $structured */
    public static function fromStructured(array $structured): self
    {
        $message = $structured['message'] ?? null;
        $reaction = $structured['reaction'] ?? null;
        $suggestedQuestValue = $structured['suggested_quest'] ?? null;

        if (! is_string($message) || blank(trim($message)) || mb_strlen($message) > 700) {
            throw new PrototypeInterviewGuideException('The guide returned an invalid message.');
        }

        if (! is_string($reaction) || ($reaction = FamiliarReaction::tryFrom($reaction)) === null) {
            throw new PrototypeInterviewGuideException('The guide returned an invalid reaction.');
        }

        $suggestedQuest = null;

        if ($suggestedQuestValue !== null) {
            if (! is_string($suggestedQuestValue) || ($suggestedQuest = PrototypeQuestKey::tryFrom($suggestedQuestValue)) === null) {
                throw new PrototypeInterviewGuideException('The guide returned an invalid quest suggestion.');
            }
        }

        return new self(trim($message), $reaction, $suggestedQuest);
    }

    /** @return array{message: string, reaction: string, suggested_quest: string|null} */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'reaction' => $this->reaction->value,
            'suggested_quest' => $this->suggestedQuest?->value,
        ];
    }
}
