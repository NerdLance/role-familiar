<?php

namespace App\Actions\Familiars;

use App\Enums\FamiliarStage;
use App\Enums\ProgressionEventType;
use App\Models\User;
use App\Models\UserFamiliar;
use Illuminate\Support\Facades\DB;

class SummonPrototypeFamiliar
{
    public function __invoke(User $user): UserFamiliar
    {
        return DB::transaction(function () use ($user): UserFamiliar {
            $familiar = UserFamiliar::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'species_key' => 'queue_wyrm',
                    'name' => 'Queue Wyrm',
                    'stage' => FamiliarStage::Summoned,
                    'summoned_at' => now(),
                ],
            );

            if ($familiar->wasRecentlyCreated) {
                $familiar->progressionEvents()->create([
                    'event_type' => ProgressionEventType::FamiliarSummoned,
                    'metadata' => ['species_key' => 'queue_wyrm'],
                    'occurred_at' => now(),
                ]);
            }

            return $familiar;
        });
    }
}
