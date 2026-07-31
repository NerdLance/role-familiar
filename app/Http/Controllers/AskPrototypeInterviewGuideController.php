<?php

namespace App\Http\Controllers;

use App\Actions\Familiars\AskPrototypeInterviewGuide;
use App\Ai\Exceptions\PrototypeInterviewGuideException;
use App\Http\Requests\AskPrototypeInterviewGuideRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class AskPrototypeInterviewGuideController extends Controller
{
    public function __invoke(
        AskPrototypeInterviewGuideRequest $request,
        AskPrototypeInterviewGuide $askGuide,
    ): JsonResponse {
        try {
            return response()->json($askGuide($request->validated('message'))->toArray());
        } catch (PrototypeInterviewGuideException) {
            return response()->json([
                'message' => 'Queue Wyrm needs a moment. Please try again soon.',
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Queue Wyrm is unavailable right now. Please try again soon.',
            ], 503);
        }
    }
}
