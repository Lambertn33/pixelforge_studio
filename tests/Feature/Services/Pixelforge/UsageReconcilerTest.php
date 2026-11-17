<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\UsageReconciler;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'pixelforge.base_url' => 'https://pixelforge.test/v1',
        'pixelforge.api_key' => 'test-key',
        'pixelforge.http.connect_timeout' => 1,
        'pixelforge.http.timeout' => 1,
        'pixelforge.http.retry.times' => 1,
        'pixelforge.http.retry.sleep_milliseconds' => 0,
    ]);

    Http::preventStrayRequests();
});

it('matches local succeeded provider costs against GET /usage', function () {
    Http::fake([
        'pixelforge.test/v1/usage*' => Http::response([
            'object' => 'usage',
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-17',
            'data' => [
                [
                    'date' => '2026-11-16',
                    'generations' => 0,
                    'input_text_tokens' => 0,
                    'input_image_tokens' => 0,
                    'output_tokens' => 0,
                    'reasoning_tokens' => 0,
                    'cost_usd' => '0.000000000',
                ],
                [
                    'date' => '2026-11-17',
                    'generations' => 1,
                    'input_text_tokens' => 12,
                    'input_image_tokens' => 0,
                    'output_tokens' => 1100,
                    'reasoning_tokens' => 500,
                    'cost_usd' => '0.050310000',
                ],
            ],
            'total' => [
                'generations' => 1,
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 500,
                'cost_usd' => '0.050310000',
            ],
        ]),
    ]);

    Generation::factory()->succeeded()->create([
        'completed_at' => '2026-11-17 12:00:00',
        'actual_provider_cost' => '0.050310000',
        'actual_customer_cost' => '0.062887500',
        'usage' => [
            'input_text_tokens' => 12,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 500,
            'total_tokens' => 1612,
        ],
    ]);

    Generation::factory()->create([
        'status' => GenerationStatus::Failed,
        'completed_at' => '2026-11-17 13:00:00',
        'actual_provider_cost' => '0.000000000',
        'actual_customer_cost' => '0.000000000',
    ]);

    $report = app(UsageReconciler::class)->reconcile(
        startDate: now('UTC')->setDate(2026, 11, 16)->startOfDay(),
        endDate: now('UTC')->setDate(2026, 11, 17)->startOfDay(),
    );

    expect($report['matched'])->toBeTrue()
        ->and($report['difference_usd'])->toBe('0.000000000')
        ->and($report['provider']['cost_usd'])->toBe('0.050310000')
        ->and($report['local']['cost_usd'])->toBe('0.050310000')
        ->and($report['local']['customer_cost_usd'])->toBe('0.062887500')
        ->and($report['local']['generations'])->toBe(1)
        ->and($report['days'][1]['matched'])->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/usage?start_date=2026-11-16&end_date=2026-11-17'
        || str_contains($request->url(), 'start_date=2026-11-16'));
});

it('reports a mismatch when local provider costs diverge', function () {
    Http::fake([
        'pixelforge.test/v1/usage*' => Http::response([
            'object' => 'usage',
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-17',
            'data' => [
                [
                    'date' => '2026-11-17',
                    'generations' => 1,
                    'input_text_tokens' => 12,
                    'input_image_tokens' => 0,
                    'output_tokens' => 1100,
                    'reasoning_tokens' => 500,
                    'cost_usd' => '0.050310000',
                ],
            ],
            'total' => [
                'generations' => 1,
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 500,
                'cost_usd' => '0.050310000',
            ],
        ]),
    ]);

    Generation::factory()->succeeded()->create([
        'completed_at' => '2026-11-17 12:00:00',
        'actual_provider_cost' => '0.040000000',
        'actual_customer_cost' => '0.050000000',
        'usage' => [
            'input_text_tokens' => 12,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 500,
            'total_tokens' => 1612,
        ],
    ]);

    $report = app(UsageReconciler::class)->reconcile(
        startDate: now('UTC')->setDate(2026, 11, 17)->startOfDay(),
        endDate: now('UTC')->setDate(2026, 11, 17)->startOfDay(),
    );

    expect($report['matched'])->toBeFalse()
        ->and($report['difference_usd'])->toBe('0.010310000')
        ->and($report['days'][0]['matched'])->toBeFalse();
});
