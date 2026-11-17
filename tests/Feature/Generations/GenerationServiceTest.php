<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Services\GenerationService;
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

it('estimates cost from live Pixelforge pricing', function () {
    $pricing = json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/pixelforge-pricing.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response($pricing),
    ]);

    $result = app(GenerationService::class)->estimate(
        prompt: str_repeat('a', 400),
        size: GenerationSize::Landscape,
        quality: GenerationQuality::High,
        referenceImages: [
            'https://example.com/one.png',
            'https://example.com/two.png',
        ],
    );

    expect($result->isEstimate)->toBeTrue()
        ->and($result->providerCostDollars())->toBe('0.373875000')
        ->and($result->customerCostDollars())->toBe('0.467343750')
        ->and($result->usage['input_text_tokens'])->toBe(115)
        ->and($result->usage['input_image_tokens'])->toBe(1530)
        ->and($result->usage['output_tokens'])->toBe(6450)
        ->and($result->usage['reasoning_tokens'])->toBe(8000);

    Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/pricing'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});
