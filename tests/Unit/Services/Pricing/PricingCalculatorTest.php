<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Services\Pricing\PricingCalculator;

/**
 * @return array<string, mixed>
 */
function pixelforgePricingFixture(): array
{
    return json_decode(
        (string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/pixelforge-pricing.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

it('matches the Pixelforge worked example for the maximum estimated cost', function () {
    $calculator = new PricingCalculator;
    $prompt = str_repeat('a', 400);

    $result = $calculator->estimate(
        prompt: $prompt,
        size: GenerationSize::Landscape,
        quality: GenerationQuality::High,
        referenceImageCount: 2,
        pricing: pixelforgePricingFixture(),
    );

    expect($result->isEstimate)->toBeTrue()
        ->and($result->usage)->toMatchArray([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
            'total_tokens' => 16095,
        ])
        ->and($result->providerCostDollars())->toBe('0.373875000')
        ->and($result->customerCostDollars())->toBe('0.467343750')
        ->and($result->providerCostNanodollars)->toBe(373_875_000)
        ->and($result->customerCostNanodollars)->toBe(467_343_750);
});

it('calculates actual cost from generation usage and frozen rates', function () {
    $calculator = new PricingCalculator;

    $result = $calculator->actual(
        usage: [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ],
        rates: [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    );

    // 12*5 + 765*10 + 6450*40 + 3184*12.50 = 60 + 7650 + 258000 + 39800 = 305510 / 1e6
    expect($result->isEstimate)->toBeFalse()
        ->and($result->providerCostDollars())->toBe('0.305510000')
        ->and($result->customerCostDollars())->toBe('0.381887500');
});

it('returns zero costs for failed generations', function () {
    $result = (new PricingCalculator)->zero();

    expect($result->providerCostNanodollars)->toBe(0)
        ->and($result->customerCostNanodollars)->toBe(0)
        ->and($result->providerCostDollars())->toBe('0.000000000')
        ->and($result->customerCostDollars())->toBe('0.000000000');
});

it('applies an exact 25 percent markup in nanodollars', function () {
    $calculator = new PricingCalculator;

    expect($calculator->applyMarkup(373_875_000))->toBe(467_343_750)
        ->and($calculator->applyMarkup(0))->toBe(0)
        ->and($calculator->applyMarkup(4))->toBe(5);
});

it('converts dollar strings to nanodollars without floating point', function () {
    expect(PricingCalculator::dollarStringToNanodollars('5.00'))->toBe(5_000_000_000)
        ->and(PricingCalculator::dollarStringToNanodollars('12.50'))->toBe(12_500_000_000)
        ->and(PricingCalculator::dollarStringToNanodollars('0.373875000'))->toBe(373_875_000)
        ->and(PricingCalculator::nanodollarsToDollarString(373_875_000))->toBe('0.373875000');
});

it('builds a pricing snapshot for persistence', function () {
    $result = (new PricingCalculator)->estimate(
        prompt: 'test',
        size: GenerationSize::Square,
        quality: GenerationQuality::Low,
        referenceImageCount: 0,
        pricing: pixelforgePricingFixture(),
    );

    $snapshot = $result->toSnapshot();

    expect($snapshot)->toHaveKeys([
        'currency',
        'rates',
        'usage',
        'provider_cost_nanodollars',
        'customer_cost_nanodollars',
        'provider_cost_usd',
        'customer_cost_usd',
        'is_estimate',
    ])->and($snapshot['is_estimate'])->toBeTrue();
});
