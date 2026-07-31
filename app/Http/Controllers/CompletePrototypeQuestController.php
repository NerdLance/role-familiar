<?php

namespace App\Http\Controllers;

use App\Actions\Familiars\CompletePrototypeQuest;
use App\Enums\PrototypeQuestKey;
use App\Http\Requests\CompletePrototypeQuestRequest;
use App\Models\UserFamiliar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CompletePrototypeQuestController extends Controller
{
    public function __invoke(
        CompletePrototypeQuestRequest $request,
        UserFamiliar $familiar,
        CompletePrototypeQuest $complete,
    ): RedirectResponse {
        Gate::authorize('update', $familiar);

        $complete(
            $familiar,
            PrototypeQuestKey::from($request->validated('quest')),
        );

        return back();
    }
}
