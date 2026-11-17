<?php

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Jobs\Pixelforge\ProcessWebhook;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\WebhookProcessor;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function frozenPricingSnapshot(): array
{
    return [
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'is_estimate' => true,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    ];
}

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

it('charges the user and stores actual costs when a generation succeeds', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01SUCCEEDEDSTATE0000000001',
        'pricing_snapshot' => frozenPricingSnapshot(),
    ]);

    CreditTransaction::factory()->purchase('10.000000000')->create([
        'user_id' => $generation->user_id,
        'balance_after' => '10.000000000',
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
    $charge = CreditTransaction::query()
        ->where('generation_id', $generation->id)
        ->where('type', CreditTransactionType::GenerationCharge)
        ->first();

    // (12*5 + 0 + 1100*40 + 500*12.50) / 1e6 = 0.050310000 provider → 0.062887500 customer
    expect($event->processed_at)->not->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->image_url)->toStartWith('https://pixelforge.test/images/done.png')
        ->and($generation->image_url_expires_at?->getTimestamp())->toBe(1762351409)
        ->and($generation->usage['total_tokens'])->toBe(1612)
        ->and($generation->actual_provider_cost)->toBe('0.050310000')
        ->and($generation->actual_customer_cost)->toBe('0.062887500')
        ->and($generation->completed_at)->not->toBeNull()
        ->and($charge)->not->toBeNull()
        ->and($charge->amount)->toBe('-0.062887500')
        ->and($charge->balance_after)->toBe('9.937112500')
        ->and($charge->idempotency_key)->toBe('generation_charge:'.$generation->id);
});

it('stores zero actual costs and does not charge when a generation fails', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01FAILEDSTATE0000000000001',
        'pricing_snapshot' => frozenPricingSnapshot(),
    ]);

    CreditTransaction::factory()->purchase('10.000000000')->create([
        'user_id' => $generation->user_id,
        'balance_after' => '10.000000000',
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
        ->and($generation->actual_provider_cost)->toBe('0.000000000')
        ->and($generation->actual_customer_cost)->toBe('0.000000000')
        ->and($generation->completed_at)->not->toBeNull()
        ->and($event->fresh()->processed_at)->not->toBeNull()
        ->and(CreditTransaction::query()->where('generation_id', $generation->id)->count())->toBe(0);
});

it('leaves a succeeded event unprocessed when frozen rates are missing', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01MISSINGRATES000000000001',
        'pricing_snapshot' => null,
    ]);

    $event = WebhookEvent::factory()->succeeded()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01MISSINGRATES000000000001',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => [
                'object' => [
                    'id' => 'gen_01MISSINGRATES000000000001',
                    'status' => 'succeeded',
                    'usage' => [
                        'input_text_tokens' => 12,
                        'input_image_tokens' => 0,
                        'output_tokens' => 1100,
                        'reasoning_tokens' => 500,
                        'total_tokens' => 1612,
                    ],
                    'metadata' => [],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($generation->fresh()->status)->toBe(GenerationStatus::Processing)
        ->and($event->fresh()->generation_id)->toBe($generation->id)
        ->and($event->fresh()->processed_at)->toBeNull()
        ->and(CreditTransaction::query()->where('generation_id', $generation->id)->count())->toBe(0);
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

it('resolves an early succeeded webhook by metadata and charges once', function () {
    $clientRequestId = (string) Str::uuid();

    $generation = Generation::factory()->create([
        'client_request_id' => $clientRequestId,
        'pixelforge_id' => null,
        'status' => GenerationStatus::Queued,
        'pricing_snapshot' => frozenPricingSnapshot(),
    ]);

    CreditTransaction::factory()->purchase('10.000000000')->create([
        'user_id' => $generation->user_id,
        'balance_after' => '10.000000000',
    ]);

    $event = WebhookEvent::factory()->succeeded()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01EARLYSUCCEEDED00000000001',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => [
                'object' => [
                    'id' => 'gen_01EARLYSUCCEEDED00000000001',
                    'status' => 'succeeded',
                    'image_url' => 'https://pixelforge.test/images/early.png?expires=1762351409&signature=abc',
                    'usage' => [
                        'input_text_tokens' => 12,
                        'input_image_tokens' => 0,
                        'output_tokens' => 1100,
                        'reasoning_tokens' => 500,
                        'total_tokens' => 1612,
                    ],
                    'completed_at' => '2026-11-17T10:00:30Z',
                    'metadata' => [
                        'client_request_id' => $clientRequestId,
                    ],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    $generation->refresh();
    $chargeCount = CreditTransaction::query()
        ->where('generation_id', $generation->id)
        ->where('type', CreditTransactionType::GenerationCharge)
        ->count();

    expect($event->fresh()->processed_at)->not->toBeNull()
        ->and($event->fresh()->generation_id)->toBe($generation->id)
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->pixelforge_id)->toBe('gen_01EARLYSUCCEEDED00000000001')
        ->and($generation->actual_customer_cost)->toBe('0.062887500')
        ->and($chargeCount)->toBe(1);
});

it('does not charge twice when a duplicate succeeded webhook arrives', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01DUPLICATESUCCEEDED0000001',
        'pricing_snapshot' => frozenPricingSnapshot(),
    ]);

    CreditTransaction::factory()->purchase('10.000000000')->create([
        'user_id' => $generation->user_id,
        'balance_after' => '10.000000000',
    ]);

    $payloadObject = [
        'id' => 'gen_01DUPLICATESUCCEEDED0000001',
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
    ];

    $first = WebhookEvent::factory()->succeeded()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01DUPLICATESUCCEEDED0000001',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => ['object' => $payloadObject],
        ],
    ]);

    $second = WebhookEvent::factory()->succeeded()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01DUPLICATESUCCEEDED0000002',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'data' => ['object' => $payloadObject],
        ],
    ]);

    $processor = app(WebhookProcessor::class);

    (new ProcessWebhook($first->id))->handle($processor);
    (new ProcessWebhook($second->id))->handle($processor);

    expect($first->fresh()->processed_at)->not->toBeNull()
        ->and($second->fresh()->processed_at)->not->toBeNull()
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and(CreditTransaction::query()
            ->where('generation_id', $generation->id)
            ->where('type', CreditTransactionType::GenerationCharge)
            ->count())->toBe(1);
});

it('does not regress a succeeded generation when a late failed event arrives', function () {
    $generation = Generation::factory()->succeeded()->create([
        'pixelforge_id' => 'gen_01LATEFAILEDSTATE0000000001',
        'image_url' => 'https://pixelforge.test/images/final.png',
        'actual_customer_cost' => '0.062887500',
    ]);

    CreditTransaction::factory()->generationCharge($generation, '-0.062887500')->create([
        'balance_after' => '9.937112500',
    ]);

    $event = WebhookEvent::factory()->failed()->create([
        'generation_id' => null,
        'payload' => [
            'id' => 'evt_01LATEFAILEDSTATE0000000001',
            'object' => 'event',
            'type' => 'generation.failed',
            'data' => [
                'object' => [
                    'id' => 'gen_01LATEFAILEDSTATE0000000001',
                    'status' => 'failed',
                    'error' => [
                        'code' => 'generation_failed',
                        'message' => 'Late failure should be ignored.',
                    ],
                    'metadata' => [],
                ],
            ],
        ],
    ]);

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->image_url)->toBe('https://pixelforge.test/images/final.png')
        ->and($generation->actual_customer_cost)->toBe('0.062887500')
        ->and($event->fresh()->processed_at)->not->toBeNull()
        ->and(CreditTransaction::query()->where('generation_id', $generation->id)->count())->toBe(1);
});
