<?php

namespace App\Actions\TargetRoles;

use App\Enums\CompetencyCategory;
use App\Enums\DataSource;
use App\Enums\Priority;
use App\Models\RoleCompetency;
use App\Models\TargetRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SyncRoleCompetencies
{
    /**
     * @param  array<int, array{id?: int|null, name: string, category: CompetencyCategory|string, priority: Priority|string, source: DataSource|string, source_evidence?: string|null, user_confidence?: int|null, user_confirmed_at?: \DateTimeInterface|null}>  $competencies
     * @return Collection<int, RoleCompetency>
     */
    public function __invoke(TargetRole $targetRole, array $competencies): Collection
    {
        return DB::transaction(function () use ($targetRole, $competencies): Collection {
            $lockedTargetRole = TargetRole::query()
                ->whereKey($targetRole->id)
                ->lockForUpdate()
                ->firstOrFail();

            $submittedIds = collect($competencies)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id);

            if ($submittedIds->isNotEmpty()) {
                $ownedIds = $lockedTargetRole->competencies()->whereKey($submittedIds)->pluck('id');

                if ($ownedIds->count() !== $submittedIds->unique()->count()) {
                    throw new \InvalidArgumentException('A competency does not belong to this target role.');
                }
            }

            $lockedTargetRole->competencies()->delete();

            foreach (array_values($competencies) as $sortOrder => $competency) {
                $lockedTargetRole->competencies()->create([
                    'name' => $competency['name'],
                    'category' => $competency['category'],
                    'priority' => $competency['priority'],
                    'source_evidence' => $competency['source_evidence'] ?? null,
                    'source' => $competency['source'],
                    'user_confidence' => $competency['user_confidence'] ?? null,
                    'sort_order' => $sortOrder,
                    'user_confirmed_at' => $competency['user_confirmed_at'] ?? null,
                ]);
            }

            return $lockedTargetRole->competencies()->get();
        });
    }
}
