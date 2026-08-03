<?php

namespace App\Actions\TargetRoles;

use App\Models\TargetRole;
use Illuminate\Support\Facades\DB;

class DeleteTargetRole
{
    public function __invoke(TargetRole $targetRole): void
    {
        DB::transaction(function () use ($targetRole): void {
            TargetRole::query()->whereKey($targetRole)->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
