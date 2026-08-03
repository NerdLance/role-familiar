<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\EmailVerificationDeliveryException;
use App\Notifications\VerifyEmail;
use Illuminate\Notifications\Notification;
use Resend\Client;
use Resend\Email;
use Throwable;

class ResendChannel
{
    public function __construct(private Client $resend) {}

    public function send(object $notifiable, Notification $notification): Email
    {
        if (! $notifiable instanceof User || ! $notification instanceof VerifyEmail) {
            throw new EmailVerificationDeliveryException;
        }

        try {
            return $this->resend->emails->send(
                $notification->toResend($notifiable),
                ['idempotency_key' => $notification->idempotencyKey()],
            );
        } catch (Throwable) {
            throw new EmailVerificationDeliveryException;
        }
    }
}
