<?php

namespace App\Http\Requests;

use App\Enums\CompetencyCategory;
use App\Enums\Priority;
use App\Enums\Seniority;
use App\Models\TargetRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TargetRoleProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $targetRole = $this->route('targetRole');
        $targetRoleId = $targetRole instanceof TargetRole ? $targetRole->id : null;

        return [
            'title' => ['required', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'seniority' => ['nullable', Rule::enum(Seniority::class)],
            'role_summary' => ['nullable', 'string', 'max:5000'],
            'interview_at' => ['nullable', 'date'],
            'biggest_concern' => ['nullable', 'string', 'max:2000'],
            'responsibilities' => ['present', 'array', 'max:20'],
            'responsibilities.*' => ['array:'.($targetRoleId === null ? 'content,priority' : 'id,content,priority')],
            'responsibilities.*.id' => $this->childIdRules('role_responsibilities', $targetRoleId),
            'responsibilities.*.content' => ['required', 'string', 'max:2000'],
            'responsibilities.*.priority' => ['required', Rule::enum(Priority::class)],
            'competencies' => ['present', 'array', 'max:30'],
            'competencies.*' => ['array:'.($targetRoleId === null ? 'name,category,priority,source_evidence,user_confidence' : 'id,name,category,priority,source_evidence,user_confidence')],
            'competencies.*.id' => $this->childIdRules('role_competencies', $targetRoleId),
            'competencies.*.name' => ['required', 'string', 'max:160'],
            'competencies.*.category' => ['required', Rule::enum(CompetencyCategory::class)],
            'competencies.*.priority' => ['required', Rule::enum(Priority::class)],
            'competencies.*.source_evidence' => ['nullable', 'string', 'max:2000'],
            'competencies.*.user_confidence' => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $responsibilities = $this->input('responsibilities', []);
        $competencies = $this->input('competencies', []);

        $this->merge([
            'title' => $this->trimmed('title'),
            'company_name' => $this->trimmedOrNull('company_name'),
            'role_summary' => $this->trimmedOrNull('role_summary'),
            'biggest_concern' => $this->trimmedOrNull('biggest_concern'),
            'responsibilities' => collect(is_array($responsibilities) ? $responsibilities : [])
                ->filter(fn (mixed $row): bool => is_array($row) && filled(trim((string) ($row['content'] ?? ''))))
                ->map(fn (array $row): array => [
                    ...$row,
                    'content' => trim((string) $row['content']),
                ])->values()->all(),
            'competencies' => collect(is_array($competencies) ? $competencies : [])
                ->filter(fn (mixed $row): bool => is_array($row) && filled(trim((string) ($row['name'] ?? ''))))
                ->map(fn (array $row): array => [
                    ...$row,
                    'name' => trim((string) $row['name']),
                    'source_evidence' => filled($row['source_evidence'] ?? null)
                        ? trim((string) $row['source_evidence'])
                        : null,
                ])->values()->all(),
        ]);
    }

    /** @return array<int, mixed> */
    private function childIdRules(string $table, ?int $targetRoleId): array
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

    private function trimmed(string $key): string
    {
        return trim((string) $this->input($key, ''));
    }

    private function trimmedOrNull(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
