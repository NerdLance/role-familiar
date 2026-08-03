<?php

namespace App\Actions\Familiars;

use App\Enums\FamiliarStage;
use App\Enums\ProgressionEventType;
use App\Models\TargetRole;
use App\Models\User;
use App\Models\UserFamiliar;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AssignQueueWyrmToTargetRole
{
    public function __invoke(User $user, TargetRole $targetRole): UserFamiliar
    {
        return DB::transaction(function () use ($user, $targetRole): UserFamiliar {
            User::query()->whereKey($user)->lockForUpdate()->firstOrFail();
            $lockedTargetRole = TargetRole::query()->whereKey($targetRole)->lockForUpdate()->firstOrFail();

            if ($lockedTargetRole->user_id !== $user->id) {
                throw new AuthorizationException('The target role does not belong to this user.');
            }

            if (! $lockedTargetRole->isActive()) {
                throw new DomainException('A Queue Wyrm may only be assigned to an active confirmed role.');
            }

            $existingFamiliar = $lockedTargetRole->familiar()->lockForUpdate()->first();

            if ($existingFamiliar instanceof UserFamiliar) {
                if ($existingFamiliar->user_id !== $user->id || $existingFamiliar->species_key !== 'queue_wyrm') {
                    throw new DomainException('The target role has an invalid familiar association.');
                }

                return $existingFamiliar;
            }

            $familiar = $user->familiars()
                ->whereNull('target_role_id')
                ->where('species_key', 'queue_wyrm')
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($familiar instanceof UserFamiliar) {
                $familiar->update(['target_role_id' => $lockedTargetRole->id]);
            } else {
                $familiar = $user->familiars()->create([
                    'target_role_id' => $lockedTargetRole->id,
                    'species_key' => 'queue_wyrm',
                    'name' => 'Queue Wyrm',
                    'stage' => FamiliarStage::Summoned,
                    'summoned_at' => now(),
                ]);

                $familiar->progressionEvents()->create([
                    'event_type' => ProgressionEventType::FamiliarSummoned,
                    'metadata' => ['species_key' => 'queue_wyrm'],
                    'occurred_at' => now(),
                ]);
            }

            $familiar->progressionEvents()->create([
                'event_type' => ProgressionEventType::TargetRoleAssigned,
                'metadata' => [
                    'species_key' => 'queue_wyrm',
                    'target_role_public_id' => $lockedTargetRole->public_id,
                ],
                'occurred_at' => now(),
            ]);

            return $familiar->refresh();
        });
    }
}
