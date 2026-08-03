<?php

namespace App\Http\Controllers;

use App\Models\UserFamiliar;
use App\Support\Familiars\ConfirmedRoleContext;
use App\Support\Familiars\ConfirmedRoleTopics;
use App\Support\Familiars\FamiliarAssetCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FamiliarController extends Controller
{
    public function __invoke(
        Request $request,
        FamiliarAssetCatalog $catalog,
        ConfirmedRoleContext $roleContext,
        ConfirmedRoleTopics $roleTopics,
    ): Response {
        $targetRole = $request->user()->targetRoles()
            ->active()
            ->with(['familiar.questProgress', 'responsibilities', 'competencies'])
            ->first();
        $familiar = $targetRole?->familiar;
        $context = $targetRole === null ? null : $roleContext->for($targetRole);

        return Inertia::render('familiar', [
            'manifest' => $catalog->publicManifest(),
            'familiar' => $familiar === null ? null : $this->publicFamiliar($familiar),
            'targetRole' => $targetRole === null
                ? null
                : [
                    'public_id' => $targetRole->public_id,
                    'title' => $targetRole->title,
                    'company_name' => $targetRole->company_name,
                    'interview_at' => $targetRole->interview_at?->toIso8601String(),
                    'status' => $targetRole->status->value,
                    'context' => $context,
                    'topics' => collect($roleTopics->for($targetRole, $context))
                        ->map(fn ($topic): array => $topic->toArray())
                        ->all(),
                    'acknowledgement' => $roleContext->acknowledgement($context),
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
