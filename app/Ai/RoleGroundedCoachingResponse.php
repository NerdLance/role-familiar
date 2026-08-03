<?php

namespace App\Ai;

use App\Ai\Exceptions\RoleGroundedCoachingException;
use App\Enums\CoachingNextActionType;
use App\Enums\FamiliarReaction;
use App\Enums\PrototypeQuestKey;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

readonly class RoleGroundedCoachingResponse
{
    /**
     * @param  array<int, array{topic: string, explanation: string}>  $roleConnections
     * @param  array<int, array{topic: string, explanation: string}>  $uncertainties
     * @param  array{type: string, label: string}|null  $suggestedNextAction
     */
    public function __construct(
        public string $message,
        public array $roleConnections,
        public array $uncertainties,
        public ?array $suggestedNextAction,
        public FamiliarReaction $reaction,
        public ?PrototypeQuestKey $suggestedQuest,
    ) {}

    /** @param array<string, mixed> $structured */
    public static function fromStructured(array $structured): self
    {
        $allowedKeys = [
            'message',
            'role_connections',
            'uncertainties',
            'suggested_next_action',
            'reaction',
            'suggested_quest',
        ];
        $actualKeys = array_keys($structured);

        if (array_diff($actualKeys, $allowedKeys) !== []
            || ! array_key_exists('message', $structured)
            || ! array_key_exists('reaction', $structured)) {
            throw new RoleGroundedCoachingException('invalid_structured_output');
        }

        $structured = self::trimStrings([
            'role_connections' => [],
            'uncertainties' => [],
            'suggested_next_action' => null,
            'suggested_quest' => null,
            ...$structured,
        ]);
        $validator = Validator::make($structured, [
            'message' => ['required', 'string', 'min:1', 'max:1200'],
            'role_connections' => ['present', 'array', 'max:3'],
            'role_connections.*' => ['array:topic,explanation'],
            'role_connections.*.topic' => ['required', 'string', 'min:1', 'max:160'],
            'role_connections.*.explanation' => ['required', 'string', 'min:1', 'max:400'],
            'uncertainties' => ['present', 'array', 'max:3'],
            'uncertainties.*' => ['array:topic,explanation'],
            'uncertainties.*.topic' => ['required', 'string', 'min:1', 'max:160'],
            'uncertainties.*.explanation' => ['required', 'string', 'min:1', 'max:400'],
            'suggested_next_action' => ['present', 'nullable', 'array:type,label'],
            'suggested_next_action.type' => ['required_with:suggested_next_action', Rule::enum(CoachingNextActionType::class)],
            'suggested_next_action.label' => ['required_with:suggested_next_action', 'string', 'min:1', 'max:120'],
            'reaction' => ['required', Rule::enum(FamiliarReaction::class)],
            'suggested_quest' => ['present', 'nullable', Rule::enum(PrototypeQuestKey::class)],
        ]);

        if ($validator->fails()) {
            throw new RoleGroundedCoachingException('invalid_structured_output');
        }

        $validated = $validator->validated();

        return new self(
            message: $validated['message'],
            roleConnections: $validated['role_connections'],
            uncertainties: $validated['uncertainties'],
            suggestedNextAction: $validated['suggested_next_action'],
            reaction: FamiliarReaction::from($validated['reaction']),
            suggestedQuest: isset($validated['suggested_quest'])
                ? PrototypeQuestKey::from($validated['suggested_quest'])
                : null,
        );
    }

    /**
     * @return array{
     *     message: string,
     *     role_connections: array<int, array{topic: string, explanation: string}>,
     *     uncertainties: array<int, array{topic: string, explanation: string}>,
     *     suggested_next_action: array{type: string, label: string}|null,
     *     reaction: string,
     *     suggested_quest: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'role_connections' => $this->roleConnections,
            'uncertainties' => $this->uncertainties,
            'suggested_next_action' => $this->suggestedNextAction,
            'reaction' => $this->reaction->value,
            'suggested_quest' => $this->suggestedQuest?->value,
        ];
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
