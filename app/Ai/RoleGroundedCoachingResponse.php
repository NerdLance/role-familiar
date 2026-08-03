<?php

namespace App\Ai;

use App\Ai\Exceptions\RoleGroundedCoachingException;
use App\Enums\FamiliarInteractionIntent;
use App\Enums\FamiliarReaction;
use App\Support\Familiars\ConfirmedRoleTopic;
use Illuminate\Support\Facades\Validator;

readonly class RoleGroundedCoachingResponse
{
    /**
     * @param  array<int, array{type: string, label: string, topic_id?: string}>  $nextActions
     */
    public function __construct(
        public string $title,
        public string $content,
        public FamiliarReaction $reaction,
        public array $nextActions,
    ) {}

    /** @param array<string, mixed> $structured */
    public static function fromStructured(
        array $structured,
        FamiliarInteractionIntent $intent,
        ?ConfirmedRoleTopic $topic,
    ): self {
        if (array_diff(array_keys($structured), ['content']) !== []) {
            throw new RoleGroundedCoachingException('schema_rejection', [
                'validation_rule' => 'unexpected_field',
            ]);
        }

        $structured = self::trimStrings($structured);
        $validator = Validator::make($structured, [
            'content' => ['required', 'string', 'min:1', 'max:1200'],
        ]);

        if ($validator->fails()) {
            $failed = $validator->failed();
            $contentFailure = array_key_first($failed['content'] ?? []);

            throw new RoleGroundedCoachingException(
                $contentFailure === 'Max' ? 'string_length_violation' : 'missing_required_field',
                ['validation_rule' => is_string($contentFailure) ? $contentFailure : 'unknown'],
            );
        }

        $validated = $validator->validated();

        return new self(
            title: $intent->title($topic?->label),
            content: $validated['content'],
            reaction: $intent->reaction(),
            nextActions: self::nextActions($intent, $topic),
        );
    }

    /**
     * @return array{
     *     title: string,
     *     content: string,
     *     reaction: string,
     *     next_actions: array<int, array{type: string, label: string, topic_id?: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'reaction' => $this->reaction->value,
            'next_actions' => $this->nextActions,
        ];
    }

    /** @return array<int, array{type: string, label: string, topic_id?: string}> */
    private static function nextActions(
        FamiliarInteractionIntent $intent,
        ?ConfirmedRoleTopic $topic,
    ): array {
        return match ($intent) {
            FamiliarInteractionIntent::Teach => self::teachNextActions($topic),
            FamiliarInteractionIntent::InterviewPrep => [[
                'type' => FamiliarInteractionIntent::PracticeTopic->value,
                'label' => 'Practice a role topic',
            ]],
            default => [],
        };
    }

    /** @return array<int, array{type: string, label: string, topic_id?: string}> */
    private static function teachNextActions(?ConfirmedRoleTopic $topic): array
    {
        $action = [
            'type' => FamiliarInteractionIntent::AskQuestion->value,
            'label' => 'Ask me a question',
        ];

        if ($topic !== null) {
            $action['topic_id'] = $topic->id;
        }

        return [$action];
    }

    private static function trimStrings(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(self::trimStrings(...), $value);
    }
}
