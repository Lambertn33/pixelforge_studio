<?php

use App\Enums\GenerationStatus;
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

it('moves a queued generation to processing', function () {
    $clientRequestId = (string) Str::uuid();

    $generation = Generation::factory()->create([
        'client_request_id' => $clientRequestId,
        'pixelforge_id' => null,
        'status' => GenerationStatus::Queued,
    ]);

    $event = WebhookEvent::factory()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01PROCESSINGSTATE000000001',
            'object' => 'event',
            'type' => 'generation.processing',
            'data' => [
                'object' => [
                    'id' => 'gen_01PROCESSINGSTATE000000001',
                    'status' => 'processing',
                    'started_at' => '2026-11-17T10:00:05Z',
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
        ->and($event->processed_at)->not->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->pixelforge_id)->toBe('gen_01PROCESSINGSTATE000000001')
        ->and($generation->started_at)->not->toBeNull();
});

it('applies a succeeded generation payload', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01SUCCEEDEDSTATE0000000001',
    ]);

    $event = WebhookEvent::factory()->succeeded()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01SUCCEEDEDSTATE0000000001',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => [
                'object' => [
                    'id' => 'gen_01SUCCEEDEDSTATE0000000001',
                    'status' => 'succeeded',
                    'image_url' => 'https://pixelforge.test/images/done.png?expires=1762351409&signature=abc',
                    'usage' => [
                        'input_text_tokens' => 12,
                        'input_image_tokens' => 0,
                        'output_tokens' => 1100,
                        'reasoning_tokens' => 500,
                        'total_tokens' => 1612,
                    ],
                    'completed_at' => '2026-11-17T10:00:30Z',
                    'metadata' => [],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    $generation->refresh();
    $event->refresh();

    expect($event->processed_at)->not->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->image_url)->toStartWith('https://pixelforge.test/images/done.png')
        ->and($generation->image_url_expires_at?->getTimestamp())->toBe(1762351409)
        ->and($generation->usage['total_tokens'])->toBe(1612)
        ->and($generation->completed_at)->not->toBeNull();
});

it('applies a failed generation payload', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01FAILEDSTATE0000000000001',
    ]);

    $event = WebhookEvent::factory()->failed()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01FAILEDSTATE0000000000001',
            'object' => 'event',
            'type' => 'generation.failed',
            'data' => [
                'object' => [
                    'id' => 'gen_01FAILEDSTATE0000000000001',
                    'status' => 'failed',
                    'error' => [
                        'code' => 'generation_failed',
                        'message' => 'The model could not produce an image for the prompt.',
                    ],
                    'completed_at' => '2026-11-17T10:00:30Z',
                    'metadata' => [],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('generation_failed')
        ->and($generation->error_message)->toBe('The model could not produce an image for the prompt.')
        ->and($generation->completed_at)->not->toBeNull()
        ->and($event->fresh()->processed_at)->not->toBeNull();
});

it('does not regress a finished generation when a late processing event arrives', function () {
    $generation = Generation::factory()->succeeded()->create([
        'pixelforge_id' => 'gen_01NOREGRESSSTATE00000000001',
        'image_url' => 'https://pixelforge.test/images/final.png',
    ]);

    $event = WebhookEvent::factory()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01LATEPROCESSING00000000001',
            'object' => 'event',
            'type' => 'generation.processing',
            'data' => [
                'object' => [
                    'id' => 'gen_01NOREGRESSSTATE00000000001',
                    'status' => 'processing',
                    'metadata' => [],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->image_url)->toBe('https://pixelforge.test/images/final.png')
        ->and($event->fresh()->processed_at)->not->toBeNull()
        ->and($event->fresh()->generation_id)->toBe($generation->id);
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
