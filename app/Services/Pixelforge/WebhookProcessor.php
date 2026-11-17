<?php

namespace App\Services\Pixelforge;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Enums\PixelforgeEventType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Pricing\PricingCalculator;
use App\Services\Pricing\PricingResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WebhookProcessor
{
    public function __construct(private PricingCalculator $pricing) {}

    public function process(int $webhookEventId): void
    {
        /** @var WebhookEvent|null $event */
        $event = WebhookEvent::query()->find($webhookEventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        if ($event->event_type === PixelforgeEventType::Ping) {
            $event->markProcessed();

            return;
        }

        $generation = $this->associateGeneration($event);

        if ($generation === null) {
            return;
        }

        if ($this->applyEvent($event, $generation)) {
            $event->markProcessed();
        }
    }

    private function associateGeneration(WebhookEvent $event): ?Generation
    {
        $object = $this->generationObject($event);

        if ($object === null) {
            return null;
        }

        $generation = $event->generation_id !== null
            ? Generation::query()->find($event->generation_id)
            : $this->findGeneration($object);

        if ($generation === null) {
            return null;
        }

        if ($event->generation_id === null) {
            $event->forceFill(['generation_id' => $generation->id])->save();
        }

        $this->backfillPixelforgeId($generation, $object);

        return $generation->fresh();
    }

    private function applyEvent(WebhookEvent $event, Generation $generation): bool
    {
        $object = $this->generationObject($event) ?? [];

        return DB::transaction(function () use ($event, $generation, $object): bool {
            /** @var Generation $locked */
            $locked = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isFinished()) {
                return true;
            }

            if ($event->event_type === PixelforgeEventType::GenerationProcessing) {
                $locked->forceFill($this->processingUpdates($locked, $object))->save();

                return true;
            }

            if ($event->event_type === PixelforgeEventType::GenerationSucceeded) {
                return $this->applySucceeded($locked, $object);
            }

            if ($event->event_type === PixelforgeEventType::GenerationFailed) {
                return $this->applyFailed($locked, $object);
            }

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function applySucceeded(Generation $locked, array $object): bool
    {
        $usage = isset($object['usage']) && is_array($object['usage'])
            ? $object['usage']
            : $locked->usage;

        $rates = data_get($locked->pricing_snapshot, 'rates');

        if (! is_array($usage) || ! is_array($rates)) {
            return false;
        }

        $currency = is_string(data_get($locked->pricing_snapshot, 'currency'))
            ? data_get($locked->pricing_snapshot, 'currency')
            : 'usd';

        $result = $this->pricing->actual(
            usage: $usage,
            rates: $rates,
            currency: $currency,
        );

        $updates = $this->succeededUpdates($locked, $object);
        $updates['usage'] = $usage;
        $updates['actual_provider_cost'] = $result->providerCostDollars();
        $updates['actual_customer_cost'] = $result->customerCostDollars();
        $updates['pricing_snapshot'] = $this->mergePricingSnapshot($locked, $result);

        $locked->forceFill($updates)->save();
        $this->recordGenerationCharge($locked, $result);

        return true;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function applyFailed(Generation $locked, array $object): bool
    {
        $currency = is_string(data_get($locked->pricing_snapshot, 'currency'))
            ? data_get($locked->pricing_snapshot, 'currency')
            : 'usd';

        $result = $this->pricing->zero($currency);

        $updates = $this->failedUpdates($locked, $object);
        $updates['actual_provider_cost'] = $result->providerCostDollars();
        $updates['actual_customer_cost'] = $result->customerCostDollars();
        $updates['pricing_snapshot'] = $this->mergePricingSnapshot($locked, $result);

        $locked->forceFill($updates)->save();

        return true;
    }

    private function recordGenerationCharge(Generation $generation, PricingResult $result): void
    {
        User::query()->whereKey($generation->user_id)->lockForUpdate()->firstOrFail();

        $previousBalance = CreditTransaction::query()
            ->where('user_id', $generation->user_id)
            ->orderByDesc('id')
            ->value('balance_after') ?? '0.000000000';

        $amount = bcmul($result->customerCostDollars(), '-1', 9);
        $balanceAfter = bcadd((string) $previousBalance, $amount, 9);

        try {
            CreditTransaction::query()->create([
                'user_id' => $generation->user_id,
                'generation_id' => $generation->id,
                'type' => CreditTransactionType::GenerationCharge,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'currency' => $result->currency,
                'idempotency_key' => 'generation_charge:'.$generation->id,
                'description' => 'Charge for successful generation',
                'metadata' => [
                    'provider_cost_usd' => $result->providerCostDollars(),
                    'customer_cost_usd' => $result->customerCostDollars(),
                ],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already charged for this generation.
        }
    }

    private function mergePricingSnapshot(Generation $generation, PricingResult $result): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $generation->pricing_snapshot ?? [];

        return [
            ...$snapshot,
            ...$result->toSnapshot(),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function processingUpdates(Generation $generation, array $object): array
    {
        return [
            'status' => GenerationStatus::Processing,
            'started_at' => $generation->started_at ?? $this->timestamp($object, 'started_at') ?? now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function succeededUpdates(Generation $generation, array $object): array
    {
        $updates = [
            'status' => GenerationStatus::Succeeded,
            'started_at' => $generation->started_at ?? $this->timestamp($object, 'started_at') ?? now(),
            'completed_at' => $this->timestamp($object, 'completed_at') ?? now(),
        ];

        if (isset($object['image_url']) && is_string($object['image_url']) && $object['image_url'] !== '') {
            $updates['image_url'] = $object['image_url'];
            $updates['image_url_expires_at'] = $this->imageUrlExpiresAt($object['image_url']);
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function failedUpdates(Generation $generation, array $object): array
    {
        $error = isset($object['error']) && is_array($object['error']) ? $object['error'] : [];

        return [
            'status' => GenerationStatus::Failed,
            'started_at' => $generation->started_at ?? $this->timestamp($object, 'started_at') ?? now(),
            'completed_at' => $this->timestamp($object, 'completed_at') ?? now(),
            'error_code' => isset($error['code']) && is_string($error['code']) ? $error['code'] : 'generation_failed',
            'error_message' => isset($error['message']) && is_string($error['message'])
                ? $error['message']
                : 'The generation failed.',
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function timestamp(array $object, string $key): ?Carbon
    {
        $value = $object[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function imageUrlExpiresAt(string $imageUrl): ?Carbon
    {
        $expires = parse_url($imageUrl, PHP_URL_QUERY);

        if (! is_string($expires) || $expires === '') {
            return null;
        }

        parse_str($expires, $query);

        if (! isset($query['expires']) || ! is_numeric($query['expires'])) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $query['expires']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function generationObject(WebhookEvent $event): ?array
    {
        $object = $event->payload['data']['object'] ?? null;

        return is_array($object) ? $object : null;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $pixelforgeId = isset($object['id']) && is_string($object['id']) && $object['id'] !== ''
            ? $object['id']
            : null;

        if ($pixelforgeId !== null) {
            $byProviderId = Generation::query()
                ->where('pixelforge_id', $pixelforgeId)
                ->first();

            if ($byProviderId !== null) {
                return $byProviderId;
            }
        }

        $clientRequestId = data_get($object, 'metadata.client_request_id');

        if (! is_string($clientRequestId) || $clientRequestId === '') {
            return null;
        }

        return Generation::query()
            ->where('client_request_id', $clientRequestId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function backfillPixelforgeId(Generation $generation, array $object): void
    {
        $pixelforgeId = isset($object['id']) && is_string($object['id']) && $object['id'] !== ''
            ? $object['id']
            : null;

        if ($pixelforgeId === null) {
            return;
        }

        DB::transaction(function () use ($generation, $pixelforgeId): void {
            /** @var Generation|null $locked */
            $locked = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->pixelforge_id !== null) {
                return;
            }

            $locked->forceFill(['pixelforge_id' => $pixelforgeId])->save();
        });
    }
}
