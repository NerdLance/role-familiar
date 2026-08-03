<?php

use App\Models\User;
use App\Notifications\Channels\ResendChannel;
use App\Notifications\EmailVerificationDeliveryException;
use App\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Resend\Client;
use Resend\Contracts\Transporter;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Resend\ValueObjects\Transporter\Payload;

final class RecordingResendTransporter implements Transporter
{
    /** @var array<int, array{payload: array<string, mixed>, idempotency_key: string}> */
    public array $requests = [];

    public ?Throwable $failure = null;

    public function request(Payload $payload): array
    {
        $request = $payload->toRequest(
            BaseUri::from('api.resend.test'),
            new Headers([]),
        );

        $this->requests[] = [
            'payload' => json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR),
            'idempotency_key' => $request->getHeaderLine('Idempotency-Key'),
        ];

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return ['id' => 'email_test_123'];
    }
}

beforeEach(function (): void {
    config([
        'services.resend.key' => 're_test_key',
        'services.resend.templates.email_verification' => 'rolefamiliar-email-verification',
    ]);
});

/** @return array{0: ResendChannel, 1: RecordingResendTransporter} */
function recordingResendChannel(): array
{
    $transporter = new RecordingResendTransporter;

    return [new ResendChannel(new Client($transporter)), $transporter];
}

test('the verification notification is queued after commit with bounded retries', function () {
    Queue::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job): bool {
        return $job->notification instanceof VerifyEmail
            && $job->notification instanceof ShouldQueueAfterCommit
            && $job->tries === 3
            && $job->timeout === 20
            && $job->backoff() === [10, 30]
            && $job->afterCommit === true;
    });
});

test('the resend channel sends only the approved hosted template payload', function () {
    [$channel, $transporter] = recordingResendChannel();
    $user = User::factory()->unverified()->create([
        'name' => 'Lance Madden',
        'email' => 'lance@example.test',
    ]);

    $channel->send($user, new VerifyEmail('delivery-payload'));

    $request = $transporter->requests[0];
    $payload = $request['payload'];

    expect($payload)->toHaveKeys(['to', 'template'])
        ->not->toHaveKeys(['html', 'text'])
        ->and(array_keys($payload))->toBe(['to', 'template'])
        ->and($payload['to'])->toBe(['lance@example.test'])
        ->and($payload['template']['id'])->toBe('rolefamiliar-email-verification')
        ->and(array_keys($payload['template']['variables']))->toBe(['first_name', 'verify_url'])
        ->and($payload['template']['variables']['first_name'])->toBe('Lance')
        ->and($payload['template']['variables'])->not->toHaveKeys(['FIRST_NAME', 'VERIFY_URL'])
        ->and($request['idempotency_key'])->toBe('email-verification/delivery-payload');
});

test('the generated verification URL uses the signed temporary verification route', function () {
    [$channel, $transporter] = recordingResendChannel();
    $user = User::factory()->unverified()->create([
        'email' => 'signed@example.test',
    ]);

    $channel->send($user, new VerifyEmail('delivery-url'));

    $verifyUrl = $transporter->requests[0]['payload']['template']['variables']['verify_url'];
    $expectedPath = route('verification.verify', [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ], absolute: false);
    $query = [];
    parse_str((string) parse_url($verifyUrl, PHP_URL_QUERY), $query);

    expect(parse_url($verifyUrl, PHP_URL_PATH))->toBe($expectedPath)
        ->and(URL::hasValidSignature(Request::create($verifyUrl)))->toBeTrue()
        ->and($query)->toHaveKeys(['expires', 'signature'])
        ->and((int) $query['expires'])->toBeGreaterThan(now()->timestamp)
        ->and((int) $query['expires'])->toBeLessThanOrEqual(now()->addMinutes(60)->timestamp);
});

test('a retry of one notification reuses its idempotency key', function () {
    [$channel, $transporter] = recordingResendChannel();
    $user = User::factory()->unverified()->create();
    $notification = new VerifyEmail('stable-delivery-id');

    $channel->send($user, $notification);
    $channel->send($user, $notification);

    expect(array_column($transporter->requests, 'idempotency_key'))
        ->toBe([
            'email-verification/stable-delivery-id',
            'email-verification/stable-delivery-id',
        ]);
});

test('separate resend notifications receive distinct idempotency keys', function () {
    [$channel, $transporter] = recordingResendChannel();
    $user = User::factory()->unverified()->create();

    $channel->send($user, new VerifyEmail);
    $channel->send($user, new VerifyEmail);

    $keys = array_column($transporter->requests, 'idempotency_key');

    expect($keys)->toHaveCount(2)
        ->and($keys[0])->not->toBe($keys[1]);
});

test('provider failures use queued retry handling and remain sanitized', function () {
    Log::spy();
    $secret = 're_private_provider_secret';
    $transporter = new RecordingResendTransporter;
    $transporter->failure = new RuntimeException("Provider response leaked {$secret}");
    $client = new Client($transporter);
    app()->instance(Client::class, $client);

    $user = User::factory()->unverified()->create();
    $notification = new VerifyEmail('failed-delivery-id');
    $job = new SendQueuedNotifications($user, $notification, [ResendChannel::class]);

    try {
        $job->handle(app(ChannelManager::class));
        $this->fail('The queued notification should have failed.');
    } catch (EmailVerificationDeliveryException $exception) {
        expect($exception->getMessage())->not->toContain($secret)
            ->and($exception->getPrevious())->toBeNull()
            ->and($job->tries)->toBe(3)
            ->and($job->backoff())->toBe([10, 30]);

        $job->failed($exception);
    }

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($secret): bool {
            $serializedLog = $message.json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'Email verification delivery failed.'
                && $context === [
                    'delivery_id' => 'failed-delivery-id',
                    'error_type' => 'EmailVerificationDeliveryException',
                ]
                && ! str_contains($serializedLog, $secret);
        });
});

test('missing Resend API key configuration fails deterministically', function () {
    config(['services.resend.key' => null]);
    app()->forgetInstance(Client::class);

    expect(fn () => app(Client::class))
        ->toThrow(LogicException::class, 'The Resend API key is not configured.');
});

test('missing template configuration fails deterministically', function () {
    config(['services.resend.templates.email_verification' => null]);
    $user = User::factory()->unverified()->create();

    expect(fn () => (new VerifyEmail)->toResend($user))
        ->toThrow(LogicException::class, 'The Resend email verification template is not configured.');
});
