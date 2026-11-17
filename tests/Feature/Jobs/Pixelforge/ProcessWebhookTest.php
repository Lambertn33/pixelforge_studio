<?php

use App\Jobs\Pixelforge\ProcessWebhook;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\WebhookProcessor;
use Illuminate\Support\Str;

it('marks ping events as processed', function () {
    $event = WebhookEvent::factory()->ping()->create();

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($event->fresh()->processed_at)->not->toBeNull()
        ->and($event->fresh()->processing_error)->toBeNull();
});

it('skips events that were already processed', function () {
    $event = WebhookEvent::factory()->ping()->processed()->create([
        'processing_error' => null,
    ]);
    $processedAt = $event->processed_at;

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($event->fresh()->processed_at?->eq($processedAt))->toBeTrue();
});

it('associates a generation by pixelforge id', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01RESOLVEBYPROVIDERID0000001',
    ]);

    $event = WebhookEvent::factory()->succeeded()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01RESOLVEBYPROVIDERID0000001',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => [
                'object' => [
                    'id' => 'gen_01RESOLVEBYPROVIDERID0000001',
                    'status' => 'succeeded',
                    'metadata' => [],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($event->fresh()->generation_id)->toBe($generation->id)
        ->and($event->fresh()->processed_at)->toBeNull();
});

it('associates a generation by client_request_id before pixelforge id exists', function () {
    $clientRequestId = (string) Str::uuid();

    $generation = Generation::factory()->create([
        'client_request_id' => $clientRequestId,
        'pixelforge_id' => null,
        'metadata' => ['client_request_id' => $clientRequestId],
    ]);

    $event = WebhookEvent::factory()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01EARLYWEBHOOK0000000000001',
            'object' => 'event',
            'type' => 'generation.processing',
            'data' => [
                'object' => [
                    'id' => 'gen_01EARLYWEBHOOK0000000000001',
                    'status' => 'processing',
                    'metadata' => [
                        'client_request_id' => $clientRequestId,
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    $generation->refresh();
    $event->refresh();

    expect($event->generation_id)->toBe($generation->id)
        ->and($generation->pixelforge_id)->toBe('gen_01EARLYWEBHOOK0000000000001')
        ->and($event->processed_at)->toBeNull();
});

it('leaves the event unprocessed when no generation can be resolved yet', function () {
    $event = WebhookEvent::factory()->succeeded()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01MISSINGGEN00000000000001',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => [
                'object' => [
                    'id' => 'gen_01MISSINGGEN00000000000001',
                    'status' => 'succeeded',
                    'metadata' => [
                        'client_request_id' => (string) Str::uuid(),
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($event->fresh()->generation_id)->toBeNull()
        ->and($event->fresh()->processed_at)->toBeNull();
});
