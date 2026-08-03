<?php

namespace App\Http\Controllers;

use App\Actions\Familiars\AskPrototypeInterviewGuide;
use App\Ai\Exceptions\PrototypeInterviewGuideException;
use App\Http\Requests\AskPrototypeInterviewGuideRequest;
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

        try {
            return response()->json($askGuide($message)->toArray());
        } catch (PrototypeInterviewGuideException $exception) {
            $reference = $this->logFailure('prototype_response', $exception, $request->user()?->id, $message, [
                'reason' => $exception->reason,
                ...$exception->context,
            ]);

            return response()->json([
                'message' => "Queue Wyrm needs a moment. Please try again soon. Reference: {$reference}",
            ], 503);
        } catch (RateLimitedException|ProviderOverloadedException|InsufficientCreditsException $exception) {
            $reference = $this->logFailure('provider_capacity', $exception, $request->user()?->id, $message);

            return response()->json([
                'message' => "Queue Wyrm is temporarily unavailable. Please try again soon. Reference: {$reference}",
            ], 503);
        } catch (Throwable $exception) {
            $reference = $this->logFailure('provider_failure', $exception, $request->user()?->id, $message);

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

        Log::warning('Prototype interview guide request failed.', [
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
