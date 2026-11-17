<?php

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Services\Pixelforge\Reconciler;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function reconcileFrozenPricingSnapshot(): array
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

beforeEach(function () {
    config([
        'pixelforge.base_url' => 'https://pixelforge.test/v1',
        'pixelforge.api_key' => 'test-key',
        'pixelforge.http.connect_timeout' => 1,
        'pixelforge.http.timeout' => 1,
        'pixelforge.http.retry.times' => 1,
        'pixelforge.http.retry.sleep_milliseconds' => 0,
        'pixelforge.reconciliation.min_age_seconds' => 60,
        'pixelforge.reconciliation.batch_size' => 50,
    ]);

    $this->freezeTime();
    Http::preventStrayRequests();
});

it('applies a succeeded provider status for a stuck generation', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01RECONCILESUCCESS000000001',
        'pricing_snapshot' => reconcileFrozenPricingSnapshot(),
        'submitted_at' => now()->subMinutes(5),
    ]);

    CreditTransaction::factory()->purchase('10.000000000')->create([
        'user_id' => $generation->user_id,
        'balance_after' => '10.000000000',
    ]);

    Http::fake([
        'pixelforge.test/v1/generations/gen_01RECONCILESUCCESS000000001' => Http::response([
            'id' => 'gen_01RECONCILESUCCESS000000001',
            'object' => 'generation',
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
        ]),
    ]);

    $result = app(Reconciler::class)->reconcile();

    $generation->refresh();
    $charge = CreditTransaction::query()
        ->where('generation_id', $generation->id)
        ->where('type', CreditTransactionType::GenerationCharge)
        ->first();

    expect($result)->toMatchArray([
        'attempted' => 1,
        'applied' => 1,
        'skipped' => 0,
        'failed' => 0,
    ])
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->actual_customer_cost)->toBe('0.062887500')
        ->and($charge)->not->toBeNull()
        ->and($charge->amount)->toBe('-0.062887500');
});

it('applies a failed provider status without charging', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01RECONCILEFAILED0000000001',
        'pricing_snapshot' => reconcileFrozenPricingSnapshot(),
        'submitted_at' => now()->subMinutes(5),
    ]);

    Http::fake([
        'pixelforge.test/v1/generations/gen_01RECONCILEFAILED0000000001' => Http::response([
            'id' => 'gen_01RECONCILEFAILED0000000001',
            'object' => 'generation',
            'status' => 'failed',
            'error' => [
                'code' => 'generation_failed',
                'message' => 'The model could not produce an image for the prompt.',
            ],
            'completed_at' => '2026-11-17T10:00:30Z',
        ]),
    ]);

    $result = app(Reconciler::class)->reconcile();

    $generation->refresh();

    expect($result['applied'])->toBe(1)
        ->and($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->actual_customer_cost)->toBe('0.000000000')
        ->and(CreditTransaction::query()->where('generation_id', $generation->id)->count())->toBe(0);
});

it('moves a queued generation to processing when the provider says so', function () {
    $generation = Generation::factory()->create([
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => 'gen_01RECONCILEPROCESSING0000001',
        'submitted_at' => now()->subMinutes(5),
    ]);

    Http::fake([
        'pixelforge.test/v1/generations/gen_01RECONCILEPROCESSING0000001' => Http::response([
            'id' => 'gen_01RECONCILEPROCESSING0000001',
            'object' => 'generation',
            'status' => 'processing',
            'started_at' => '2026-11-17T10:00:05Z',
        ]),
    ]);

    app(Reconciler::class)->reconcile();

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->started_at)->not->toBeNull();
});

it('continues the batch when one provider request fails', function () {
    $failing = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01RECONCILEHTTPFAIL000000001',
        'submitted_at' => now()->subMinutes(5),
    ]);

    $succeeding = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01RECONCILEHTTPOK00000000001',
        'pricing_snapshot' => reconcileFrozenPricingSnapshot(),
        'submitted_at' => now()->subMinutes(4),
    ]);

    Http::fake([
        'pixelforge.test/v1/generations/gen_01RECONCILEHTTPFAIL000000001' => Http::response([
            'error' => [
                'code' => 'internal_error',
                'message' => 'Temporary failure',
            ],
        ], 500),
        'pixelforge.test/v1/generations/gen_01RECONCILEHTTPOK00000000001' => Http::response([
            'id' => 'gen_01RECONCILEHTTPOK00000000001',
            'object' => 'generation',
            'status' => 'failed',
            'error' => [
                'code' => 'generation_failed',
                'message' => 'Failed.',
            ],
            'completed_at' => '2026-11-17T10:00:30Z',
        ]),
    ]);

    $result = app(Reconciler::class)->reconcile();

    expect($result)->toMatchArray([
        'attempted' => 2,
        'applied' => 1,
        'skipped' => 0,
        'failed' => 1,
    ])
        ->and($failing->fresh()->status)->toBe(GenerationStatus::Processing)
        ->and($succeeding->fresh()->status)->toBe(GenerationStatus::Failed);
});
