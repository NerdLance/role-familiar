<?php

namespace App\Actions\TargetRoles;

use App\Enums\TargetRoleStatus;
use App\Models\TargetRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmTargetRole
{
    public function __invoke(TargetRole $targetRole): TargetRole
    {
        return DB::transaction(function () use ($targetRole): TargetRole {
            User::query()->whereKey($targetRole->user_id)->lockForUpdate()->firstOrFail();
            $lockedTargetRole = TargetRole::query()->whereKey($targetRole)->lockForUpdate()->firstOrFail();

            $errors = [];

            if (blank($lockedTargetRole->title)) {
                $errors['title'] = 'A title is required before confirmation.';
            }

            if (! $lockedTargetRole->responsibilities()->whereNotNull('content')->where('content', '!=', '')->exists()) {
                $errors['responsibilities'] = 'Add at least one responsibility before confirmation.';
            }

            if (! $lockedTargetRole->competencies()->whereNotNull('name')->where('name', '!=', '')->exists()) {
                $errors['competencies'] = 'Add at least one competency before confirmation.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

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

            $confirmedAt = $lockedTargetRole->confirmed_at ?? now();
            $lockedTargetRole->update([
                'status' => TargetRoleStatus::Confirmed,
                'confirmed_at' => $confirmedAt,
                'archived_at' => null,
            ]);
            $lockedTargetRole->responsibilities()->update(['user_confirmed_at' => $confirmedAt]);
            $lockedTargetRole->competencies()->update(['user_confirmed_at' => $confirmedAt]);

            return $lockedTargetRole->refresh()->load(['responsibilities', 'competencies']);
        });
    }
}
