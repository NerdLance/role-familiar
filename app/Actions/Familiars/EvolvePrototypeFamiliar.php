<?php

namespace App\Actions\Familiars;

use App\Enums\FamiliarStage;
use App\Enums\ProgressionEventType;
use App\Enums\PrototypeQuestKey;
use App\Models\UserFamiliar;

class EvolvePrototypeFamiliar
{
    public function __invoke(UserFamiliar $familiar): UserFamiliar
    {
        $completedQuestCount = $familiar->questProgress()
            ->whereNotNull('completed_at')
            ->count();

        $targetStage = match ($familiar->stage) {
            FamiliarStage::Summoned => $completedQuestCount >= 2
                ? FamiliarStage::Attuned
                : null,
            FamiliarStage::Attuned => $completedQuestCount >= count(PrototypeQuestKey::cases())
                ? FamiliarStage::InterviewReady
                : null,
            FamiliarStage::InterviewReady => null,
        };

        if ($targetStage === null) {
            return $familiar;
        }

        $timestampColumn = $targetStage === FamiliarStage::Attuned
            ? 'attuned_at'
            : 'interview_ready_at';

        $familiar->update([
            'stage' => $targetStage,
            $timestampColumn => $familiar->{$timestampColumn} ?? now(),
        ]);

        $familiar->progressionEvents()->create([
            'event_type' => ProgressionEventType::FamiliarEvolved,
            'metadata' => [
                'from' => $targetStage === FamiliarStage::Attuned
                    ? FamiliarStage::Summoned->value
                    : FamiliarStage::Attuned->value,
                'to' => $targetStage->value,
            ],
            'occurred_at' => now(),
        ]);

        return $familiar->refresh();
    }
}
