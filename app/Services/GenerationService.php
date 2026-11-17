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
use Illuminate\Support\Facades\DB;
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

        return $this->applyProviderCreateResponse($generation, $providerGeneration);
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

    /**
     * Attach fields from the Pixelforge create response without clobbering a newer local state.
     *
     * @param  array<string, mixed>  $providerGeneration
     */
    public function applyProviderCreateResponse(Generation $generation, array $providerGeneration): Generation
    {
        return DB::transaction(function () use ($generation, $providerGeneration): Generation {
            /** @var Generation $locked */
            $locked = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $updates = [];

            if ($locked->pixelforge_id === null && isset($providerGeneration['id']) && is_string($providerGeneration['id'])) {
                $updates['pixelforge_id'] = $providerGeneration['id'];
            }

            if (isset($providerGeneration['rates']) && is_array($providerGeneration['rates'])) {
                /** @var array<string, mixed> $snapshot */
                $snapshot = $locked->pricing_snapshot ?? [];
                $snapshot['rates'] = $providerGeneration['rates'];
                $snapshot['provider_rates_source'] = 'create_response';
                $updates['pricing_snapshot'] = $snapshot;
            }

            if (! $locked->isFinished()) {
                $providerStatus = isset($providerGeneration['status']) && is_string($providerGeneration['status'])
                    ? GenerationStatus::tryFrom($providerGeneration['status'])
                    : null;

                if (
                    $providerStatus !== null
                    && $this->canApplyCreateStatus($locked->status, $providerStatus)
                ) {
                    $updates['status'] = $providerStatus;
                }
            }

            if ($updates !== []) {
                $locked->forceFill($updates)->save();
            }

            return $locked->refresh();
        });
    }

    private function canApplyCreateStatus(GenerationStatus $current, GenerationStatus $incoming): bool
    {
        if ($current->isFinished() || $incoming->isFinished()) {
            return false;
        }

        if ($current === GenerationStatus::Processing && $incoming === GenerationStatus::Queued) {
            return false;
        }

        return true;
    }
}
