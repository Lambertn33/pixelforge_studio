<?php

namespace App\Services;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Exceptions\GenerationProviderException;
use App\Exceptions\Pixelforge\PixelforgeRequestException;
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

        try {
            $providerGeneration = $this->createWithProvider($generation);
        } catch (PixelforgeRequestException $exception) {
            $failed = $this->markFailedFromProvider($generation, $exception);

            throw GenerationProviderException::fromProvider($failed, $exception);
        }

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
     * Persist a failed local generation from a Pixelforge create/request error.
     *
     * Failed generations are never billed. Skips updates when the row is already finished.
     */
    public function markFailedFromProvider(Generation $generation, PixelforgeRequestException $exception): Generation
    {
        $errorCode = $this->providerErrorCode($exception);
        $errorMessage = $this->userFacingProviderMessage($exception);
        $zero = $this->pricing->zero();

        return DB::transaction(function () use ($generation, $errorCode, $errorMessage, $zero): Generation {
            /** @var Generation $locked */
            $locked = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isFinished()) {
                return $locked;
            }

            $locked->forceFill([
                'status' => GenerationStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'actual_provider_cost' => $zero->providerCostDollars(),
                'actual_customer_cost' => $zero->customerCostDollars(),
                'completed_at' => now(),
            ])->save();

            return $locked->refresh();
        });
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

    private function providerErrorCode(PixelforgeRequestException $exception): string
    {
        if ($exception->errorCode !== null && $exception->errorCode !== '') {
            return $exception->errorCode;
        }

        return match (true) {
            $exception->status === 0 => 'connection_failed',
            $exception->status === 400 => 'content_policy_violation',
            $exception->status === 401 => 'unauthorized',
            $exception->status === 404 => 'not_found',
            $exception->status === 409 => 'idempotency_conflict',
            $exception->status === 422 => 'validation_failed',
            $exception->status === 429 => 'rate_limited',
            $exception->status === 503 => 'provider_overloaded',
            $exception->status >= 500 => 'provider_error',
            default => 'provider_error',
        };
    }

    private function userFacingProviderMessage(PixelforgeRequestException $exception): string
    {
        if ($exception->status === 429) {
            if ($exception->retryAfterSeconds !== null && $exception->retryAfterSeconds > 0) {
                return "Too many requests right now. Please wait {$exception->retryAfterSeconds} seconds and try again.";
            }

            return 'Too many requests right now. Please wait a moment and try again.';
        }

        return match (true) {
            $exception->status === 0 => 'Could not reach the image service. Please try again.',
            $exception->status === 400 => 'This prompt was rejected by the content policy. Try a different prompt.',
            $exception->status === 401 => 'Image generation is temporarily unavailable. Please try again later.',
            $exception->status === 404 => 'The image service could not find that resource.',
            $exception->status === 409 => 'This request conflicts with a previous submission. Please try again.',
            $exception->isValidationError() => $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'The request was invalid. Check your prompt and options.',
            $exception->status === 503 => 'The image service is overloaded. Please try again shortly.',
            $exception->status >= 500 => 'The image service had a problem. Please try again shortly.',
            default => 'Image generation failed. Please try again.',
        };
    }
}
