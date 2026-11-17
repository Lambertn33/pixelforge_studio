<?php

namespace App\Services;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pricing\PricingCalculator;
use App\Services\Pricing\PricingResult;
use Illuminate\Support\Str;

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

    /**
     * Persist a queued local generation with estimate before calling Pixelforge.
     *
     * @param  list<string>  $referenceImages
     */
    public function createLocalGeneration(
        User $user,
        string $prompt,
        GenerationSize $size,
        GenerationQuality $quality,
        array $referenceImages = [],
    ): Generation {
        $estimate = $this->estimate($prompt, $size, $quality, $referenceImages);
        $clientRequestId = (string) Str::uuid();

        return Generation::query()->create([
            'user_id' => $user->id,
            'client_request_id' => $clientRequestId,
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
            'metadata' => [
                'client_request_id' => $clientRequestId,
            ],
            'status' => GenerationStatus::Queued,
            'estimated_provider_cost' => $estimate->providerCostDollars(),
            'estimated_customer_cost' => $estimate->customerCostDollars(),
            'pricing_snapshot' => $estimate->toSnapshot(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Create the local generation, then submit it to Pixelforge.
     *
     * The local row is committed before the HTTP request starts.
     *
     * @param  list<string>  $referenceImages
     */
    public function submit(
        User $user,
        string $prompt,
        GenerationSize $size,
        GenerationQuality $quality,
        array $referenceImages = [],
    ): Generation {
        $generation = $this->createLocalGeneration(
            user: $user,
            prompt: $prompt,
            size: $size,
            quality: $quality,
            referenceImages: $referenceImages,
        );

        $providerGeneration = $this->createWithProvider($generation);

        $generation->forceFill([
            'pixelforge_id' => $providerGeneration['id'],
        ])->save();

        return $generation->refresh();
    }

    /**
     * Submit an existing local generation to Pixelforge.
     *
     * @return array<string, mixed>
     */
    public function createWithProvider(Generation $generation): array
    {
        $payload = [
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'metadata' => [
                'client_request_id' => $generation->client_request_id,
            ],
        ];

        if ($generation->reference_images !== null && $generation->reference_images !== []) {
            $payload['reference_images'] = $generation->reference_images;
        }

        return $this->pixelforge->createGeneration(
            $payload,
            $generation->client_request_id,
        );
    }
}
