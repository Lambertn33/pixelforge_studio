<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

/**
 * @return array<string, mixed>
 */
function generationServicePricingFixture(): array
{
    return json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/pixelforge-pricing.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function fakePixelforgePricing(): void
{
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response(generationServicePricingFixture()),
    ]);
}

/**
 * @param  array<string, mixed>  $generationOverrides
 */
function fakePixelforgePricingAndCreate(array $generationOverrides = []): void
{
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response(generationServicePricingFixture()),
        'pixelforge.test/v1/generations' => Http::response(array_merge([
            'id' => 'gen_01TESTGENERATION000000000001',
            'object' => 'generation',
            'status' => 'queued',
            'prompt' => 'A lighthouse on a cliff at dawn',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => [],
            'metadata' => [],
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'usage' => null,
            'error' => null,
        ], $generationOverrides), 201),
    ]);
}

it('estimates cost from live Pixelforge pricing', function () {
    fakePixelforgePricing();

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

it('persists a queued local generation with estimate before calling Pixelforge', function () {
    fakePixelforgePricing();

    $user = User::factory()->create();
    $prompt = str_repeat('a', 400);
    $referenceImages = [
        'https://example.com/one.png',
        'https://example.com/two.png',
    ];

    $generation = app(GenerationService::class)->createLocalGeneration(
        user: $user,
        prompt: $prompt,
        size: GenerationSize::Landscape,
        quality: GenerationQuality::High,
        referenceImages: $referenceImages,
    );

    expect($generation)->toBeInstanceOf(Generation::class)
        ->and($generation->user_id)->toBe($user->id)
        ->and($generation->prompt)->toBe($prompt)
        ->and($generation->size)->toBe(GenerationSize::Landscape)
        ->and($generation->quality)->toBe(GenerationQuality::High)
        ->and($generation->reference_images)->toBe($referenceImages)
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBeNull()
        ->and(Str::isUuid($generation->client_request_id))->toBeTrue()
        ->and($generation->metadata['client_request_id'])->toBe($generation->client_request_id)
        ->and($generation->estimated_provider_cost)->toBe('0.373875000')
        ->and($generation->estimated_customer_cost)->toBe('0.467343750')
        ->and($generation->pricing_snapshot['is_estimate'])->toBeTrue()
        ->and($generation->submitted_at)->not->toBeNull();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/pricing');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/generations'));
});

it('submits a local generation to Pixelforge with an idempotency key', function () {
    $user = User::factory()->create();
    $prompt = 'A lighthouse on a cliff at dawn';
    $referenceImages = ['https://example.com/style.png'];

    fakePixelforgePricingAndCreate([
        'prompt' => $prompt,
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => $referenceImages,
        'metadata' => [
            'client_request_id' => 'will-be-overridden-by-assert',
        ],
    ]);

    $generation = app(GenerationService::class)->submit(
        user: $user,
        prompt: $prompt,
        size: GenerationSize::Landscape,
        quality: GenerationQuality::High,
        referenceImages: $referenceImages,
    );

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBe('gen_01TESTGENERATION000000000001')
        ->and($generation->client_request_id)->not->toBeEmpty();

    Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/pricing');

    Http::assertSent(function ($request) use ($generation, $prompt, $referenceImages) {
        if ($request->url() !== 'https://pixelforge.test/v1/generations' || $request->method() !== 'POST') {
            return false;
        }

        return $request->hasHeader('Idempotency-Key', $generation->client_request_id)
            && $request['prompt'] === $prompt
            && $request['size'] === '1536x1024'
            && $request['quality'] === 'high'
            && $request['reference_images'] === $referenceImages
            && $request['metadata']['client_request_id'] === $generation->client_request_id;
    });
});
