<?php

use App\Enums\PixelforgeEventType;
use App\Jobs\Pixelforge\ProcessWebhook;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config([
        'pixelforge.webhook_secret' => 'whsec_test_secret_for_signature_verifier',
        'pixelforge.webhook_tolerance_seconds' => 300,
    ]);

    Carbon::setTestNow(Carbon::createFromTimestamp(1_700_000_000));
    Queue::fake([ProcessWebhook::class]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $headers
 */
function postSignedPixelforgeWebhook(array $payload, array $headers = []): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    [, $signature] = signedPixelforgeWebhook($body, Carbon::now()->getTimestamp());

    return test()->call(
        'POST',
        '/api/webhooks/pixelforge',
        content: $body,
        server: array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $signature,
            'HTTP_PIXELFORGE_EVENT_ID' => (string) ($payload['id'] ?? ''),
            'HTTP_PIXELFORGE_EVENT_TYPE' => (string) ($payload['type'] ?? ''),
        ], $headers),
    );
}

it('accepts a signed webhook, persists the event, and queues processing', function () {
    $payload = [
        'id' => 'evt_01TESTPING000000000000001',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => '2026-11-17T10:00:00Z',
        'data' => ['object' => ['ok' => true]],
    ];

    $response = postSignedPixelforgeWebhook($payload);

    $response->assertNoContent();

    $event = WebhookEvent::query()->where('event_id', 'evt_01TESTPING000000000000001')->first();

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe(PixelforgeEventType::Ping)
        ->and($event->generation_id)->toBeNull()
        ->and($event->payload['id'])->toBe('evt_01TESTPING000000000000001')
        ->and($event->processed_at)->toBeNull()
        ->and($event->received_at)->not->toBeNull();

    Queue::assertPushed(ProcessWebhook::class, fn (ProcessWebhook $job): bool => $job->webhookEventId === $event->id);
});

it('returns success for duplicate deliveries without creating another row', function () {
    $payload = [
        'id' => 'evt_01TESTDUPLICATE0000000001',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => '2026-11-17T10:00:00Z',
        'data' => ['object' => null],
    ];

    postSignedPixelforgeWebhook($payload)->assertNoContent();
    postSignedPixelforgeWebhook($payload)->assertNoContent();

    expect(WebhookEvent::query()->where('event_id', 'evt_01TESTDUPLICATE0000000001')->count())->toBe(1);

    // Still unique-locked while the first job sits unprocessed on the queue.
    Queue::assertPushed(ProcessWebhook::class, 1);
});

it('does not queue processing again when the duplicate was already processed', function () {
    $event = WebhookEvent::factory()->ping()->processed()->create([
        'event_id' => 'evt_01TESTALREADYDONE00000001',
    ]);

    postSignedPixelforgeWebhook([
        'id' => $event->event_id,
        'object' => 'event',
        'type' => 'ping',
        'created_at' => '2026-11-17T10:00:00Z',
        'data' => ['object' => null],
    ])->assertNoContent();

    Queue::assertNothingPushed();
    expect(WebhookEvent::query()->where('event_id', $event->event_id)->count())->toBe(1);
});

it('queues processing again for an unprocessed duplicate after the unique lock is free', function () {
    $event = WebhookEvent::factory()->succeeded()->create([
        'event_id' => 'evt_01TESTNEEDRETRY0000000001',
        'processed_at' => null,
    ]);

    postSignedPixelforgeWebhook([
        'id' => $event->event_id,
        'object' => 'event',
        'type' => 'generation.succeeded',
        'created_at' => '2026-11-17T10:00:00Z',
        'data' => [
            'object' => [
                'id' => 'gen_01TESTNEEDRETRY00000000001',
                'status' => 'succeeded',
            ],
        ],
    ])->assertNoContent();

    expect(WebhookEvent::query()->where('event_id', $event->event_id)->count())->toBe(1);
    Queue::assertPushed(ProcessWebhook::class, fn (ProcessWebhook $job): bool => $job->webhookEventId === $event->id);
});

it('rejects a webhook with a missing signature', function () {
    $response = $this->postJson('/api/webhooks/pixelforge', [
        'id' => 'evt_01TESTPING000000000000001',
        'type' => 'ping',
    ]);

    $response->assertBadRequest()
        ->assertSee('Invalid Pixelforge signature.');

    expect(WebhookEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects a webhook with an invalid signature', function () {
    $payload = '{"id":"evt_01TESTPING000000000000001","type":"ping"}';

    $response = $this->call(
        'POST',
        '/api/webhooks/pixelforge',
        content: $payload,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => 't='.Carbon::now()->getTimestamp().',v1='.str_repeat('0', 64),
        ],
    );

    $response->assertBadRequest()
        ->assertSee('Invalid Pixelforge signature.');

    expect(WebhookEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects a signed webhook with an invalid payload', function () {
    $body = '{"not":"an event"}';
    [, $signature] = signedPixelforgeWebhook($body, Carbon::now()->getTimestamp());

    $response = $this->call(
        'POST',
        '/api/webhooks/pixelforge',
        content: $body,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $signature,
        ],
    );

    $response->assertBadRequest()
        ->assertSee('Webhook event id is missing.');

    expect(WebhookEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('does not require a csrf token', function () {
    postSignedPixelforgeWebhook([
        'id' => 'evt_01TESTPING000000000000002',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => '2026-11-17T10:00:00Z',
        'data' => ['object' => null],
    ])->assertNoContent();
});
