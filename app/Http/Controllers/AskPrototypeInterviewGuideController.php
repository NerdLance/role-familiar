<?php

namespace App\Http\Controllers;

use App\Actions\Familiars\AskPrototypeInterviewGuide;
use App\Ai\Exceptions\RoleGroundedCoachingException;
use App\Enums\FamiliarInteractionIntent;
use App\Http\Requests\AskPrototypeInterviewGuideRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AskPrototypeInterviewGuideController extends Controller
{
    public function __invoke(
        AskPrototypeInterviewGuideRequest $request,
        AskPrototypeInterviewGuide $askGuide,
    ): JsonResponse {
        $validated = $request->validated();
        $intent = FamiliarInteractionIntent::from($validated['intent']);
        $topicId = is_string($validated['topic_id'] ?? null) ? $validated['topic_id'] : null;
        $question = is_string($validated['question'] ?? null) ? $validated['question'] : null;
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            return response()->json($askGuide($user, $intent, $topicId, $question)->toArray());
        } catch (RoleGroundedCoachingException $exception) {
            if ($exception->reason === 'active_role_required') {
                return response()->json([
                    'message' => 'Confirm a target role before asking Queue Wyrm for coaching.',
                ], 409);
            }

            if ($exception->reason === 'active_role_familiar_required') {
                return response()->json([
                    'message' => 'Queue Wyrm is not available for the active role yet.',
                ], 409);
            }

            if ($exception->reason === 'topic_unavailable') {
                return response()->json([
                    'message' => 'That topic is no longer available. Choose a current role topic and try again.',
                ], 409);
            }

            if ($exception->reason === 'provider_rate_limit') {
                $reference = $this->logFailure('provider_capacity', $exception, $user->id, $question, [
                    'reason' => $exception->reason,
                    ...$exception->context,
                ]);

                return response()->json([
                    'message' => "Queue Wyrm is receiving too many requests. Please try again soon. Reference: {$reference}",
                ], 429)->header('Retry-After', '30');
            }

            $reference = $this->logFailure('coaching_response', $exception, $user->id, $question, [
                'reason' => $exception->reason,
                ...$exception->context,
            ]);

            return response()->json([
                'message' => "Queue Wyrm needs a moment. Please try again soon. Reference: {$reference}",
            ], 503);
        } catch (Throwable $exception) {
            $reference = $this->logFailure('provider_failure', $exception, $user->id, $question);

            return response()->json([
                'message' => "Queue Wyrm is unavailable right now. Please try again soon. Reference: {$reference}",
            ], 503);
        }
    }

    /** @param array<string, bool|int|string> $context */
    private function logFailure(
        string $category,
        Throwable $exception,
        ?int $userId,
        ?string $question,
        array $context = [],
    ): string {
        $reference = (string) Str::uuid7();

        Log::warning('Role-grounded coaching request failed.', [
            'reference' => $reference,
            'category' => $category,
            'exception_class' => $exception::class,
            'exception_code' => $exception->getCode(),
            'previous_exception_class' => $exception->getPrevious()
                ? $exception->getPrevious()::class
                : null,
            'user_id' => $userId,
            'question_length' => $question === null ? 0 : mb_strlen($question),
            ...$context,
        ]);

        return $reference;
    }
}
