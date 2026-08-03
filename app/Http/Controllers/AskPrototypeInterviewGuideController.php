<?php

namespace App\Http\Controllers;

use App\Actions\Familiars\AskPrototypeInterviewGuide;
use App\Ai\Exceptions\RoleGroundedCoachingException;
use App\Http\Requests\AskPrototypeInterviewGuideRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class AskPrototypeInterviewGuideController extends Controller
{
    public function __invoke(
        AskPrototypeInterviewGuideRequest $request,
        AskPrototypeInterviewGuide $askGuide,
    ): JsonResponse {
        $message = $request->validated('message');
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            return response()->json($askGuide($user, $message)->toArray());
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

            $reference = $this->logFailure('coaching_response', $exception, $user->id, $message, [
                'reason' => $exception->reason,
                ...$exception->context,
            ]);

            return response()->json([
                'message' => "Queue Wyrm needs a moment. Please try again soon. Reference: {$reference}",
            ], 503);
        } catch (RateLimitedException|ProviderOverloadedException|InsufficientCreditsException $exception) {
            $reference = $this->logFailure('provider_capacity', $exception, $user->id, $message);

            return response()->json([
                'message' => "Queue Wyrm is temporarily unavailable. Please try again soon. Reference: {$reference}",
            ], 503);
        } catch (Throwable $exception) {
            $reference = $this->logFailure('provider_failure', $exception, $user->id, $message);

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
        string $message,
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
            'message_length' => mb_strlen($message),
            ...$context,
        ]);

        return $reference;
    }
}
