<?php

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Services\Pixelforge\StatusApplier;

/**
 * @return array<string, mixed>
 */
function statusApplierFrozenPricingSnapshot(): array
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

/**
 * @return array<string, mixed>
 */
function succeededProviderObject(): array
{
    return [
        'id' => 'gen_01STATUSAPPLIER000000000001',
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
    ];
}

it('does not regress a finished generation when a late processing status arrives', function () {
    $generation = Generation::factory()->succeeded()->create([
        'pixelforge_id' => 'gen_01STATUSAPPLIER000000000001',
        'image_url' => 'https://pixelforge.test/images/final.png',
        'actual_customer_cost' => '0.057656250',
    ]);

    $applied = app(StatusApplier::class)->apply(
        $generation,
        GenerationStatus::Processing,
        [
            'id' => 'gen_01STATUSAPPLIER000000000001',
            'status' => 'processing',
            'started_at' => '2026-11-17T10:00:05Z',
        ],
    );

    $generation->refresh();

    expect($applied)->toBeTrue()
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->image_url)->toBe('https://pixelforge.test/images/final.png')
        ->and($generation->actual_customer_cost)->toBe('0.057656250');
});

it('does not charge twice when a succeeded status is applied again', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01STATUSAPPLIER000000000001',
        'pricing_snapshot' => statusApplierFrozenPricingSnapshot(),
    ]);

    CreditTransaction::factory()->purchase('10.000000000')->create([
        'user_id' => $generation->user_id,
        'balance_after' => '10.000000000',
    ]);

    $applier = app(StatusApplier::class);
    $object = succeededProviderObject();

    expect($applier->apply($generation, GenerationStatus::Succeeded, $object))->toBeTrue();

    $generation->refresh();

    expect($applier->apply($generation, GenerationStatus::Succeeded, $object))->toBeTrue()
        ->and(CreditTransaction::query()
            ->where('generation_id', $generation->id)
            ->where('type', CreditTransactionType::GenerationCharge)
            ->count())->toBe(1)
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('returns false when a succeeded status is missing frozen rates', function () {
    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01STATUSAPPLIER000000000001',
        'pricing_snapshot' => null,
    ]);

    $applied = app(StatusApplier::class)->apply(
        $generation,
        GenerationStatus::Succeeded,
        succeededProviderObject(),
    );

    expect($applied)->toBeFalse()
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Processing)
        ->and(CreditTransaction::query()->where('generation_id', $generation->id)->count())->toBe(0);
});

it('does not replace a succeeded generation with a late failed status', function () {
    $generation = Generation::factory()->succeeded()->create([
        'pixelforge_id' => 'gen_01STATUSAPPLIER000000000001',
        'image_url' => 'https://pixelforge.test/images/final.png',
        'actual_customer_cost' => '0.057656250',
    ]);

    $applied = app(StatusApplier::class)->apply(
        $generation,
        GenerationStatus::Failed,
        [
            'id' => 'gen_01STATUSAPPLIER000000000001',
            'status' => 'failed',
            'error' => [
                'code' => 'generation_failed',
                'message' => 'Late failure should be ignored.',
            ],
        ],
    );

    $generation->refresh();

    expect($applied)->toBeTrue()
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->image_url)->toBe('https://pixelforge.test/images/final.png')
        ->and($generation->actual_customer_cost)->toBe('0.057656250')
        ->and($generation->error_code)->toBeNull();
});
