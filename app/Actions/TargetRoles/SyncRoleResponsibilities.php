<?php

namespace App\Actions\TargetRoles;

use App\Enums\DataSource;
use App\Enums\Priority;
use App\Models\RoleResponsibility;
use App\Models\TargetRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SyncRoleResponsibilities
{
    /**
     * @param  array<int, array{content: string, priority: Priority|string, source: DataSource|string, source_evidence?: string|null, user_confirmed_at?: \DateTimeInterface|null}>  $responsibilities
     * @return Collection<int, RoleResponsibility>
     */
    public function __invoke(TargetRole $targetRole, array $responsibilities): Collection
    {
        return DB::transaction(function () use ($targetRole, $responsibilities): Collection {
            $lockedTargetRole = TargetRole::query()
                ->whereKey($targetRole->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTargetRole->responsibilities()->delete();

            foreach (array_values($responsibilities) as $sortOrder => $responsibility) {
                $lockedTargetRole->responsibilities()->create([
                    'content' => $responsibility['content'],
                    'priority' => $responsibility['priority'],
                    'source' => $responsibility['source'],
                    'source_evidence' => $responsibility['source_evidence'] ?? null,
                    'sort_order' => $sortOrder,
                    'user_confirmed_at' => $responsibility['user_confirmed_at'] ?? null,
                ]);
            }

            return $lockedTargetRole->responsibilities()->get();
        });
    }
}
