<?php

namespace App\Actions\TargetRoles;

use App\Enums\DataSource;
use App\Models\TargetRole;
use Illuminate\Support\Facades\DB;

class UpdateTargetRole
{
    public function __construct(
        private readonly SyncRoleResponsibilities $syncResponsibilities,
        private readonly SyncRoleCompetencies $syncCompetencies,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(TargetRole $targetRole, array $attributes): TargetRole
    {
        return DB::transaction(function () use ($targetRole, $attributes): TargetRole {
            $lockedTargetRole = TargetRole::query()->whereKey($targetRole)->lockForUpdate()->firstOrFail();
            $lockedTargetRole->update(collect($attributes)->only([
                'title',
                'company_name',
                'seniority',
                'role_summary',
                'interview_at',
                'biggest_concern',
            ])->all());

            $confirmedAt = $lockedTargetRole->isConfirmed() ? now() : null;
            ($this->syncResponsibilities)($lockedTargetRole, $this->responsibilities($attributes, $confirmedAt));
            ($this->syncCompetencies)($lockedTargetRole, $this->competencies($attributes, $confirmedAt));

            return $lockedTargetRole->refresh()->load(['responsibilities', 'competencies']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, array{id?: int|null, content: string, priority: string, source: DataSource, user_confirmed_at: \DateTimeInterface|null}>
     */
    private function responsibilities(array $attributes, ?\DateTimeInterface $confirmedAt): array
    {
        $rows = $attributes['responsibilities'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        $responsibilities = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['content'] ?? null) || ! is_string($row['priority'] ?? null)) {
                continue;
            }

            $responsibilities[] = [
                'id' => is_int($row['id'] ?? null) ? $row['id'] : null,
                'content' => $row['content'],
                'priority' => $row['priority'],
                'source' => DataSource::User,
                'user_confirmed_at' => $confirmedAt,
            ];
        }

        return $responsibilities;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, array{id?: int|null, name: string, category: string, priority: string, source_evidence?: string|null, user_confidence?: int|null, source: DataSource, user_confirmed_at: \DateTimeInterface|null}>
     */
    private function competencies(array $attributes, ?\DateTimeInterface $confirmedAt): array
    {
        $rows = $attributes['competencies'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        $competencies = [];

        foreach ($rows as $row) {
            if (! is_array($row)
                || ! is_string($row['name'] ?? null)
                || ! is_string($row['category'] ?? null)
                || ! is_string($row['priority'] ?? null)) {
                continue;
            }

            $competencies[] = [
                'id' => is_int($row['id'] ?? null) ? $row['id'] : null,
                'name' => $row['name'],
                'category' => $row['category'],
                'priority' => $row['priority'],
                'source_evidence' => is_string($row['source_evidence'] ?? null) ? $row['source_evidence'] : null,
                'user_confidence' => is_int($row['user_confidence'] ?? null) ? $row['user_confidence'] : null,
                'source' => DataSource::User,
                'user_confirmed_at' => $confirmedAt,
            ];
        }

        return $competencies;
    }
}
