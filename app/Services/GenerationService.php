<?php

namespace App\Services;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pricing\PricingCalculator;
use App\Services\Pricing\PricingResult;

class GenerationService
{
    public function __construct(
        private PixelforgeClient $pixelforge,
        private PricingCalculator $pricing,
    ) {}

    /**
     * Conservative cost estimate from live Pixelforge pricing.
     *
     * @param  list<string>  $referenceImages
     */
    public function estimate(
        string $prompt,
        GenerationSize $size,
        GenerationQuality $quality,
        array $referenceImages = [],
    ): PricingResult {
        return $this->pricing->estimate(
            prompt: $prompt,
            size: $size,
            quality: $quality,
            referenceImageCount: count($referenceImages),
            pricing: $this->pixelforge->pricing(),
        );
    }
}
