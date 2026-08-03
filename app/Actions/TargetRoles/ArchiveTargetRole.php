<?php

namespace App\Actions\TargetRoles;

use App\Enums\TargetRoleStatus;
use App\Models\TargetRole;
use Illuminate\Support\Facades\DB;

class ArchiveTargetRole
{
    public function __invoke(TargetRole $targetRole): TargetRole
    {
        return DB::transaction(function () use ($targetRole): TargetRole {
            $lockedTargetRole = TargetRole::query()->whereKey($targetRole)->lockForUpdate()->firstOrFail();
            $lockedTargetRole->update([
                'status' => TargetRoleStatus::Archived,
                'archived_at' => now(),
            ]);

            return $lockedTargetRole->refresh();
        });
    }
}
