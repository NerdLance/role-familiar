<?php

namespace App\Support\Familiars;

use App\Enums\FamiliarTopicType;
use App\Enums\Priority;
use App\Models\TargetRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConfirmedRoleTopics
{
    private const int MaximumTopics = 8;

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, ConfirmedRoleTopic>
     */
    public function for(TargetRole $targetRole, array $context): array
    {
        $topics = collect()
            ->merge($this->namedTopics($targetRole, $context['competencies'] ?? [], FamiliarTopicType::Competency))
            ->merge($this->namedTopics($targetRole, $context['technologies'] ?? [], FamiliarTopicType::Technology))
            ->merge($this->contentTopics($targetRole, $context['responsibilities'] ?? []))
            ->merge($this->focusAreaTopics($targetRole, $context['interview_focus_areas'] ?? []))
            ->merge($this->clarificationTopics($targetRole, $context['unresolved_ambiguities'] ?? []))
            ->unique(fn (ConfirmedRoleTopic $topic): string => $topic->type->value.'|'.Str::lower($topic->label))
            ->sort(function (ConfirmedRoleTopic $left, ConfirmedRoleTopic $right): int {
                $priorityComparison = $this->priorityRank($right->priority) <=> $this->priorityRank($left->priority);

                return $priorityComparison !== 0 ? $priorityComparison : $left->label <=> $right->label;
            })
            ->take(self::MaximumTopics)
            ->values();

        /** @var array<int, ConfirmedRoleTopic> $resolved */
        $resolved = $topics->all();

        return $resolved;
    }

    /** @param array<string, mixed> $context */
    public function resolve(TargetRole $targetRole, array $context, string $topicId): ?ConfirmedRoleTopic
    {
        return collect($this->for($targetRole, $context))
            ->first(fn (ConfirmedRoleTopic $topic): bool => hash_equals($topic->id, $topicId));
    }

    /** @param array<string, mixed> $context */
    public function select(TargetRole $targetRole, array $context): ?ConfirmedRoleTopic
    {
        return $this->for($targetRole, $context)[0] ?? null;
    }

    /**
     * @return Collection<int, ConfirmedRoleTopic>
     */
    private function namedTopics(TargetRole $targetRole, mixed $items, FamiliarTopicType $type): Collection
    {
        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['name'] ?? null))
            ->map(fn (array $item): ConfirmedRoleTopic => $this->topic(
                $targetRole,
                $type,
                $item['name'],
                $this->priority($item['priority'] ?? null),
            ));
    }

    /**
     * @return Collection<int, ConfirmedRoleTopic>
     */
    private function contentTopics(TargetRole $targetRole, mixed $items): Collection
    {
        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['content'] ?? null))
            ->map(fn (array $item): ConfirmedRoleTopic => $this->topic(
                $targetRole,
                FamiliarTopicType::Responsibility,
                $item['content'],
                $this->priority($item['priority'] ?? null),
            ));
    }

    /**
     * @return Collection<int, ConfirmedRoleTopic>
     */
    private function focusAreaTopics(TargetRole $targetRole, mixed $items): Collection
    {
        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['name'] ?? null))
            ->map(fn (array $item): ConfirmedRoleTopic => $this->topic(
                $targetRole,
                FamiliarTopicType::FocusArea,
                $item['name'],
                Priority::High,
            ));
    }

    /**
     * @return Collection<int, ConfirmedRoleTopic>
     */
    private function clarificationTopics(TargetRole $targetRole, mixed $items): Collection
    {
        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['question'] ?? null))
            ->map(fn (array $item): ConfirmedRoleTopic => $this->topic(
                $targetRole,
                FamiliarTopicType::Clarification,
                $item['question'],
                Priority::Medium,
            ));
    }

    private function topic(
        TargetRole $targetRole,
        FamiliarTopicType $type,
        string $label,
        Priority $priority,
    ): ConfirmedRoleTopic {
        $canonicalLabel = (string) Str::of($label)->squish()->limit(100, '');
        $fingerprint = implode('|', [
            $targetRole->public_id,
            $type->value,
            $priority->value,
            Str::lower($canonicalLabel),
        ]);

        return new ConfirmedRoleTopic(
            id: 'topic_'.Str::substr(hash_hmac('sha256', $fingerprint, (string) config('app.key')), 0, 24),
            label: $canonicalLabel,
            type: $type,
            priority: $priority,
        );
    }

    private function priority(mixed $priority): Priority
    {
        return is_string($priority) ? Priority::tryFrom($priority) ?? Priority::Medium : Priority::Medium;
    }

    private function priorityRank(Priority $priority): int
    {
        return match ($priority) {
            Priority::Critical => 4,
            Priority::High => 3,
            Priority::Medium => 2,
            Priority::Low => 1,
        };
    }
}
