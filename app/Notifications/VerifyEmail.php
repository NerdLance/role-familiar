<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\ResendChannel;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class VerifyEmail extends BaseVerifyEmail implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public readonly string $deliveryId;

    public function __construct(?string $deliveryId = null)
    {
        $this->deliveryId = $deliveryId ?? (string) Str::uuid();
    }

    /** @return array<int, class-string> */
    public function via(mixed $notifiable): array
    {
        return [ResendChannel::class];
    }

    /**
     * @return array{
     *     to: array{string},
     *     template: array{
     *         id: string,
     *         variables: array{first_name: string, verify_url: string}
     *     }
     * }
     */
    public function toResend(User $notifiable): array
    {
        $template = config('services.resend.templates.email_verification');

        if (! is_string($template) || blank($template)) {
            throw new LogicException('The Resend email verification template is not configured.');
        }

        $firstName = (string) Str::of($notifiable->name)
            ->squish()
            ->before(' ');

        if (blank($firstName)) {
            throw new LogicException('The verification email recipient name is not configured.');
        }

        return [
            'to' => [$notifiable->getEmailForVerification()],
            'template' => [
                'id' => $template,
                'variables' => [
                    'first_name' => $firstName,
                    'verify_url' => $this->verificationUrl($notifiable),
                ],
            ],
        ];
    }

    public function idempotencyKey(): string
    {
        return 'email-verification/'.$this->deliveryId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Email verification delivery failed.', [
            'delivery_id' => $this->deliveryId,
            'error_type' => class_basename($exception),
        ]);
    }
}
