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

        if (! is_string($message)) {
            throw new PrototypeInterviewGuideException('message_not_string', [
                'response_message_type' => get_debug_type($message),
            ]);
        }

        if (blank(trim($message))) {
            throw new PrototypeInterviewGuideException('message_empty', [
                'response_message_length' => mb_strlen($message),
            ]);
        }

        if (mb_strlen($message) > 1200) {
            throw new PrototypeInterviewGuideException('message_too_long', [
                'response_message_length' => mb_strlen($message),
            ]);
        }

        if (! is_string($reaction) || ($reaction = FamiliarReaction::tryFrom($reaction)) === null) {
            throw new PrototypeInterviewGuideException('invalid_reaction', [
                'response_reaction_type' => get_debug_type($structured['reaction'] ?? null),
            ]);
        }

        $suggestedQuest = null;

        if ($suggestedQuestValue !== null) {
            if (! is_string($suggestedQuestValue) || ($suggestedQuest = PrototypeQuestKey::tryFrom($suggestedQuestValue)) === null) {
                throw new PrototypeInterviewGuideException('invalid_quest_suggestion', [
                    'response_quest_type' => get_debug_type($suggestedQuestValue),
                ]);
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
