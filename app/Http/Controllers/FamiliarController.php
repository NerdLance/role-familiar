<?php

namespace App\Http\Controllers;

use App\Actions\Familiars\SummonPrototypeFamiliar;
use App\Models\UserFamiliar;
use App\Support\Familiars\FamiliarAssetCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FamiliarController extends Controller
{
    public function __invoke(
        Request $request,
        SummonPrototypeFamiliar $summon,
        FamiliarAssetCatalog $catalog,
    ): Response {
        $familiar = $summon($request->user())->load('questProgress');
        $targetRole = $request->user()->targetRoles()->active()->first();

        return Inertia::render('familiar', [
            'manifest' => $catalog->publicManifest(),
            'familiar' => $this->publicFamiliar($familiar),
            'targetRole' => $targetRole === null
                ? null
                : [
                    'public_id' => $targetRole->public_id,
                    'title' => $targetRole->title,
                    'status' => $targetRole->status->value,
                ],
        ]);
    }

    /** @return array<string, mixed> */
    private function publicFamiliar(UserFamiliar $familiar): array
    {
        return [
            'public_id' => $familiar->public_id,
            'name' => $familiar->name,
            'species_key' => $familiar->species_key,
            'stage' => $familiar->stage->value,
            'summoned_at' => $familiar->summoned_at->toIso8601String(),
            'attuned_at' => $familiar->attuned_at?->toIso8601String(),
            'interview_ready_at' => $familiar->interview_ready_at?->toIso8601String(),
            'quest_progress' => $familiar->questProgress
                ->map(fn ($progress): array => [
                    'quest_key' => $progress->quest_key->value,
                    'completed_at' => $progress->completed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
