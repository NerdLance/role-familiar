<?php

namespace App\Support\Familiars;

use App\Enums\CompetencyCategory;
use App\Models\RoleAnalysisReview;
use App\Models\TargetRole;
use DomainException;

class ConfirmedRoleContext
{
    /**
     * @return array{
     *     role_title: string,
     *     company: string|null,
     *     seniority: string|null,
     *     summary: string,
     *     responsibilities: array<int, array{content: string, priority: string}>,
     *     competencies: array<int, array{name: string, category: string, priority: string, user_confidence: int|null}>,
     *     technologies: array<int, array{name: string, priority: string}>,
     *     interview_focus_areas: array<int, array{name: string, reason: string}>,
     *     unresolved_ambiguities: array<int, array{question: string, reason: string}>
     * }
     */
    public function for(TargetRole $targetRole): array
    {
        if (! $targetRole->isActive()
            || ! is_string($targetRole->title)
            || ! is_string($targetRole->role_summary)
            || blank($targetRole->role_summary)) {
            throw new DomainException('Familiar context requires an active confirmed target role.');
        }

        $targetRole->loadMissing(['responsibilities', 'competencies']);
        $confirmedResponsibilities = $targetRole->responsibilities
            ->whereNotNull('user_confirmed_at')
            ->values();
        $confirmedCompetencies = $targetRole->competencies
            ->whereNotNull('user_confirmed_at')
            ->values();
        $roleReview = RoleAnalysisReview::query()
            ->whereHas('roleAnalysis', fn ($query) => $query->whereBelongsTo($targetRole))
            ->latest('applied_at')
            ->first();
        $reviewedPayload = $roleReview instanceof RoleAnalysisReview
            ? $roleReview->reviewed_payload
            : [];

        return [
            'role_title' => $targetRole->title,
            'company' => $targetRole->company_name,
            'seniority' => $targetRole->seniority?->value,
            'summary' => $targetRole->role_summary,
            'responsibilities' => $confirmedResponsibilities->map(fn ($responsibility): array => [
                'content' => $responsibility->content,
                'priority' => $responsibility->priority->value,
            ])->all(),
            'competencies' => $confirmedCompetencies
                ->reject(fn ($competency): bool => $competency->category === CompetencyCategory::Tooling)
                ->map(fn ($competency): array => [
                    'name' => $competency->name,
                    'category' => $competency->category->value,
                    'priority' => $competency->priority->value,
                    'user_confidence' => $competency->user_confidence,
                ])->values()->all(),
            'technologies' => $confirmedCompetencies
                ->filter(fn ($competency): bool => $competency->category === CompetencyCategory::Tooling)
                ->map(fn ($competency): array => [
                    'name' => $competency->name,
                    'priority' => $competency->priority->value,
                ])->values()->all(),
            'interview_focus_areas' => $this->focusAreas($reviewedPayload),
            'unresolved_ambiguities' => $this->unresolvedAmbiguities($reviewedPayload),
        ];
    }

    /** @param array<string, mixed> $context */
    public function acknowledgement(array $context): string
    {
        $themes = [];

        foreach (['competencies', 'technologies'] as $key) {
            $items = $context[$key] ?? [];

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (is_array($item) && is_string($item['name'] ?? null) && $item['name'] !== '') {
                    $themes[$item['name']] = $item['name'];
                }
            }
        }

        if ($themes === []) {
            $responsibilities = $context['responsibilities'] ?? [];

            if (is_array($responsibilities)) {
                foreach ($responsibilities as $responsibility) {
                    if (is_array($responsibility)
                        && is_string($responsibility['content'] ?? null)
                        && $responsibility['content'] !== '') {
                        $themes[$responsibility['content']] = $responsibility['content'];
                    }
                }
            }
        }

        if ($themes === []) {
            return 'I have mapped the role. We will prepare one step at a time.';
        }

        return 'I have mapped the role. We will prepare for '.$this->sentenceList(array_slice(array_values($themes), 0, 3)).'.';
    }

    /**
     * @param  array<string, mixed>  $reviewedPayload
     * @return array<int, array{name: string, reason: string}>
     */
    private function focusAreas(array $reviewedPayload): array
    {
        $focusAreas = $reviewedPayload['interview_focus_areas'] ?? [];

        if (! is_array($focusAreas)) {
            return [];
        }

        return collect($focusAreas)->filter(fn (mixed $focusArea): bool => is_array($focusArea)
            && is_string($focusArea['name'] ?? null)
            && is_string($focusArea['reason'] ?? null))
            ->map(fn (array $focusArea): array => [
                'name' => $focusArea['name'],
                'reason' => $focusArea['reason'],
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $reviewedPayload
     * @return array<int, array{question: string, reason: string}>
     */
    private function unresolvedAmbiguities(array $reviewedPayload): array
    {
        $clarifications = $reviewedPayload['clarifications'] ?? [];

        if (! is_array($clarifications)) {
            return [];
        }

        return collect($clarifications)->filter(fn (mixed $clarification): bool => is_array($clarification)
            && ($clarification['status'] ?? null) === 'unresolved'
            && is_string($clarification['question'] ?? null)
            && is_string($clarification['reason'] ?? null))
            ->map(fn (array $clarification): array => [
                'question' => $clarification['question'],
                'reason' => $clarification['reason'],
            ])->values()->all();
    }

    /** @param array<int, string> $items */
    private function sentenceList(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
