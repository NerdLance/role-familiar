<?php

namespace App\Ai;

use App\Ai\Exceptions\JobListingAnalysisException;
use App\Enums\CompetencyCategory;
use App\Enums\Priority;
use App\Enums\Seniority;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class JobListingAnalysisResult
{
    /** @param array<string, mixed> $data */
    private function __construct(public readonly array $data) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        $data = self::trimStrings($data);
        $requiredKeys = [
            'normalized_title',
            'company_name',
            'seniority',
            'role_summary',
            'responsibilities',
            'competencies',
            'technologies_and_tools',
            'interview_focus_areas',
            'ambiguities',
            'assumptions',
            'overall_extraction_confidence',
        ];
        $priorities = array_column(Priority::cases(), 'value');
        $categories = array_column(CompetencyCategory::cases(), 'value');
        $seniorities = array_column(Seniority::cases(), 'value');
        $sources = ['job_listing', 'ai_inferred'];
        $areas = ['title', 'company', 'seniority', 'responsibility', 'competency', 'tooling', 'interview_focus', 'other'];

        $validator = Validator::make($data, [
            'normalized_title' => ['present', 'nullable', 'string', 'min:1', 'max:160'],
            'company_name' => ['present', 'nullable', 'string', 'min:1', 'max:160'],
            'seniority' => ['required', Rule::in($seniorities)],
            'role_summary' => ['required', 'string', 'min:1', 'max:1200'],
            'responsibilities' => ['present', 'array', 'max:20'],
            'responsibilities.*' => ['array:content,priority,evidence,source,confidence'],
            'responsibilities.*.content' => ['required', 'string', 'max:500'],
            'responsibilities.*.priority' => ['required', Rule::in($priorities)],
            'responsibilities.*.evidence' => ['nullable', 'string', 'max:300'],
            'responsibilities.*.source' => ['required', Rule::in($sources)],
            'responsibilities.*.confidence' => ['required', 'integer', 'between:0,100'],
            'competencies' => ['present', 'array', 'max:30'],
            'competencies.*' => ['array:name,category,priority,evidence,source,confidence'],
            'competencies.*.name' => ['required', 'string', 'max:160'],
            'competencies.*.category' => ['required', Rule::in($categories)],
            'competencies.*.priority' => ['required', Rule::in($priorities)],
            'competencies.*.evidence' => ['nullable', 'string', 'max:300'],
            'competencies.*.source' => ['required', Rule::in($sources)],
            'competencies.*.confidence' => ['required', 'integer', 'between:0,100'],
            'technologies_and_tools' => ['present', 'array', 'max:20'],
            'technologies_and_tools.*' => ['array:name,priority,evidence,source,confidence'],
            'technologies_and_tools.*.name' => ['required', 'string', 'max:160'],
            'technologies_and_tools.*.priority' => ['required', Rule::in($priorities)],
            'technologies_and_tools.*.evidence' => ['nullable', 'string', 'max:300'],
            'technologies_and_tools.*.source' => ['required', Rule::in($sources)],
            'technologies_and_tools.*.confidence' => ['required', 'integer', 'between:0,100'],
            'interview_focus_areas' => ['present', 'array', 'max:12'],
            'interview_focus_areas.*' => ['array:focus,rationale,priority,evidence,source,confidence'],
            'interview_focus_areas.*.focus' => ['required', 'string', 'max:160'],
            'interview_focus_areas.*.rationale' => ['required', 'string', 'max:500'],
            'interview_focus_areas.*.priority' => ['required', Rule::in($priorities)],
            'interview_focus_areas.*.evidence' => ['nullable', 'string', 'max:300'],
            'interview_focus_areas.*.source' => ['required', Rule::in($sources)],
            'interview_focus_areas.*.confidence' => ['required', 'integer', 'between:0,100'],
            'ambiguities' => ['present', 'array', 'max:12'],
            'ambiguities.*' => ['array:area,question,reason'],
            'ambiguities.*.area' => ['required', Rule::in($areas)],
            'ambiguities.*.question' => ['required', 'string', 'max:300'],
            'ambiguities.*.reason' => ['required', 'string', 'max:500'],
            'assumptions' => ['present', 'array', 'max:12'],
            'assumptions.*' => ['array:area,statement,reason'],
            'assumptions.*.area' => ['required', Rule::in($areas)],
            'assumptions.*.statement' => ['required', 'string', 'max:300'],
            'assumptions.*.reason' => ['required', 'string', 'max:500'],
            'overall_extraction_confidence' => ['required', 'integer', 'between:0,100'],
        ]);

        $actualKeys = array_keys($data);
        sort($actualKeys);
        sort($requiredKeys);

        if ($actualKeys !== $requiredKeys || $validator->fails() || ! self::hasConsistentSources($data)) {
            throw new JobListingAnalysisException('invalid_structured_output');
        }

        return new self($validator->validated());
    }

    /** @param array<string, mixed> $data */
    private static function hasConsistentSources(array $data): bool
    {
        $hasInference = false;

        foreach (['responsibilities', 'competencies', 'technologies_and_tools', 'interview_focus_areas'] as $group) {
            foreach ($data[$group] ?? [] as $item) {
                if (($item['source'] ?? null) === 'job_listing' && blank($item['evidence'] ?? null)) {
                    return false;
                }

                $hasInference = $hasInference || ($item['source'] ?? null) === 'ai_inferred';
            }
        }

        return ! $hasInference || ! empty($data['assumptions']);
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
