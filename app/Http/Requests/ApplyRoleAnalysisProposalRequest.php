<?php

namespace App\Http\Requests;

use App\Enums\CompetencyCategory;
use App\Enums\Priority;
use App\Enums\Seniority;
use App\Models\RoleAnalysis;
use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyRoleAnalysisProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetRole = $this->route('targetRole');
        $analysis = $this->route('analysis');

        return $targetRole instanceof TargetRole
            && $analysis instanceof RoleAnalysis
            && $analysis->target_role_id === $targetRole->id
            && $this->user()?->can('applyAnalysis', $targetRole) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $targetRole = $this->route('targetRole');

        return self::rulesFor($targetRole instanceof TargetRole ? $targetRole->id : null);
    }

    /** @return array<string, mixed> */
    public static function rulesFor(?int $targetRoleId): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'seniority' => ['required', Rule::enum(Seniority::class)],
            'role_summary' => ['required', 'string', 'max:5000'],
            'interview_at' => ['nullable', 'date'],
            'biggest_concern' => ['nullable', 'string', 'max:2000'],
            'responsibilities' => ['present', 'array', 'max:20'],
            'responsibilities.*' => ['array:id,proposal_index,content,priority,evidence,confidence,inferred'],
            'responsibilities.*.id' => self::childIdRules('role_responsibilities', $targetRoleId),
            'responsibilities.*.proposal_index' => ['nullable', 'integer', 'min:0', 'distinct'],
            'responsibilities.*.content' => ['required', 'string', 'max:2000'],
            'responsibilities.*.priority' => ['required', Rule::enum(Priority::class)],
            'responsibilities.*.evidence' => ['nullable', 'string', 'max:300'],
            'responsibilities.*.confidence' => ['nullable', 'integer', 'between:0,100'],
            'responsibilities.*.inferred' => ['required', 'boolean'],
            'competencies' => ['present', 'array', 'max:50'],
            'competencies.*' => ['array:id,proposal_type,proposal_index,name,category,priority,evidence,confidence,inferred,user_confidence'],
            'competencies.*.id' => self::childIdRules('role_competencies', $targetRoleId),
            'competencies.*.proposal_type' => ['nullable', Rule::in(['competency', 'technology'])],
            'competencies.*.proposal_index' => ['nullable', 'integer', 'min:0'],
            'competencies.*.name' => ['required', 'string', 'max:160'],
            'competencies.*.category' => ['required', Rule::enum(CompetencyCategory::class)],
            'competencies.*.priority' => ['required', Rule::enum(Priority::class)],
            'competencies.*.evidence' => ['nullable', 'string', 'max:300'],
            'competencies.*.confidence' => ['nullable', 'integer', 'between:0,100'],
            'competencies.*.inferred' => ['required', 'boolean'],
            'competencies.*.user_confidence' => ['nullable', 'integer', 'between:1,5'],
            'ambiguities' => ['present', 'array', 'max:12'],
            'ambiguities.*' => ['array:index,status,answer'],
            'ambiguities.*.index' => ['required', 'integer', 'min:0', 'distinct'],
            'ambiguities.*.status' => ['required', Rule::in(['unresolved', 'answered', 'not_relevant'])],
            'ambiguities.*.answer' => ['nullable', 'required_if:ambiguities.*.status,answered', 'string', 'max:1000'],
            'assumptions' => ['present', 'array', 'max:12'],
            'assumptions.*' => ['array:index,status,correction'],
            'assumptions.*.index' => ['required', 'integer', 'min:0', 'distinct'],
            'assumptions.*.status' => ['required', Rule::in(['unresolved', 'accepted', 'corrected', 'removed'])],
            'assumptions.*.correction' => ['nullable', 'required_if:assumptions.*.status,corrected', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'company_name' => $this->trimmedOrNull('company_name'),
            'role_summary' => trim((string) $this->input('role_summary', '')),
            'biggest_concern' => $this->trimmedOrNull('biggest_concern'),
            'responsibilities' => $this->cleanRows('responsibilities', 'content'),
            'competencies' => $this->cleanRows('competencies', 'name'),
            'ambiguities' => $this->cleanDecisionRows('ambiguities', 'answer'),
            'assumptions' => $this->cleanDecisionRows('assumptions', 'correction'),
        ]);
    }

    /** @return array<int, mixed> */
    private static function childIdRules(string $table, ?int $targetRoleId): array
    {
        if ($targetRoleId === null) {
            return ['prohibited'];
        }

        return [
            'nullable',
            'integer',
            'distinct',
            Rule::exists($table, 'id')->where('target_role_id', $targetRoleId),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function cleanRows(string $key, string $contentKey): array
    {
        $rows = $this->input($key, []);

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled(trim((string) ($row[$contentKey] ?? ''))))
            ->map(fn (array $row): array => [
                ...$row,
                $contentKey => trim((string) $row[$contentKey]),
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function cleanDecisionRows(string $key, string $textKey): array
    {
        $rows = $this->input($key, []);

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                ...$row,
                $textKey => filled($row[$textKey] ?? null) ? trim((string) $row[$textKey]) : null,
            ])->values()->all();
    }

    private function trimmedOrNull(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
