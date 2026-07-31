<?php

namespace App\Http\Controllers;

use App\Support\Familiars\FamiliarAssetCatalog;
use Inertia\Inertia;
use Inertia\Response;

class FamiliarLabController extends Controller
{
    public function __invoke(FamiliarAssetCatalog $catalog): Response
    {
        return Inertia::render('familiar-lab', [
            'manifest' => $catalog->publicManifest(),
            'stageLabels' => [
                'stage-1' => 'Summoned',
                'stage-2' => 'Attuned',
                'stage-3' => 'Interview-ready',
            ],
            'quests' => [
                ['key' => 'scroll-1', 'label' => 'Practice a behavioral story'],
                ['key' => 'scroll-2', 'label' => 'Structure a STAR response'],
                ['key' => 'observatory-lens', 'label' => 'Research the target company'],
                ['key' => 'magical-book', 'label' => 'Review a technical concept'],
            ],
        ]);
    }
}
