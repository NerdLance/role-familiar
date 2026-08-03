<?php

namespace App\Actions\TargetRoles;

use App\Enums\TargetRoleStatus;
use App\Models\TargetRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreTargetRole
{
    public function __invoke(TargetRole $targetRole): TargetRole
    {
        return DB::transaction(function () use ($targetRole): TargetRole {
            User::query()->whereKey($targetRole->user_id)->lockForUpdate()->firstOrFail();
            $lockedTargetRole = TargetRole::withTrashed()->whereKey($targetRole)->lockForUpdate()->firstOrFail();

            if ($lockedTargetRole->trashed()) {
                $lockedTargetRole->restore();
            }

            if ($lockedTargetRole->isArchived()) {
                TargetRole::query()
                    ->ownedBy($lockedTargetRole->user)
                    ->active()
                    ->whereKeyNot($lockedTargetRole->id)
                    ->lockForUpdate()
                    ->get()
                    ->each->update([
                        'status' => TargetRoleStatus::Archived,
                        'archived_at' => now(),
                    ]);

                $lockedTargetRole->update([
                    'status' => TargetRoleStatus::Confirmed,
                    'archived_at' => null,
                ]);
            }

            return $lockedTargetRole->refresh();
        });
    }
}
