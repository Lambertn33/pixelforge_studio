<?php

use App\Models\Generation;
use App\Models\User;
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
});

function fakeUsagePayload(): array
{
    return [
        'object' => 'usage',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-02',
        'data' => [
            [
                'date' => '2026-11-01',
                'generations' => 1,
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 500,
                'cost_usd' => '0.050310000',
            ],
            [
                'date' => '2026-11-02',
                'generations' => 0,
                'input_text_tokens' => 0,
                'input_image_tokens' => 0,
                'output_tokens' => 0,
                'reasoning_tokens' => 0,
                'cost_usd' => '0.000000000',
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
    ];
}

it('redirects guests away from the usage page', function () {
    $this->get(route('usage.index'))->assertRedirect(route('login'));
});

it('renders the usage reconciliation page for authenticated users', function () {
    Http::fake([
        'pixelforge.test/v1/usage*' => Http::response(fakeUsagePayload()),
    ]);

    $user = User::factory()->create();

    Generation::factory()->succeeded()->create([
        'completed_at' => '2026-11-01 15:00:00',
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

    $this->actingAs($user)
        ->get(route('usage.index', [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usage/Index')
            ->where('report.matched', true)
            ->where('report.provider.cost_usd', '0.050310000')
            ->where('report.local.cost_usd', '0.050310000')
            ->where('filters.start_date', '2026-11-01')
            ->where('filters.end_date', '2026-11-02')
            ->has('report.days', 2));
});

it('rejects an invalid usage date range', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('usage.index', [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('end_date');
});
