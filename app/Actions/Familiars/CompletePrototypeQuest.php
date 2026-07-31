<?php

namespace App\Actions\Familiars;

use App\Enums\ProgressionEventType;
use App\Enums\PrototypeQuestKey;
use App\Models\UserFamiliar;
use Illuminate\Support\Facades\DB;

class CompletePrototypeQuest
{
    public function __construct(private EvolvePrototypeFamiliar $evolve) {}

    public function __invoke(UserFamiliar $familiar, PrototypeQuestKey $quest): UserFamiliar
    {
        return DB::transaction(function () use ($familiar, $quest): UserFamiliar {
            $lockedFamiliar = UserFamiliar::query()
                ->whereKey($familiar->id)
                ->lockForUpdate()
                ->firstOrFail();

            $progress = $lockedFamiliar->questProgress()->firstOrCreate(
                ['quest_key' => $quest->value],
                ['completed_at' => now()],
            );

            if ($progress->wasRecentlyCreated) {
                $lockedFamiliar->progressionEvents()->create([
                    'event_type' => ProgressionEventType::QuestCompleted,
                    'metadata' => ['quest_key' => $quest->value],
                    'occurred_at' => now(),
                ]);
            }

            return ($this->evolve)($lockedFamiliar);
        });
    }
}
