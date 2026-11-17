<?php

namespace App\Services\Pixelforge;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pricing\PricingCalculator;
use App\Services\Pricing\PricingResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatusApplier
{
    public function __construct(private PricingCalculator $pricing) {}

    /**
     * @param  array<string, mixed>  $object
     */
    public function apply(Generation $generation, GenerationStatus $providerStatus, array $object): bool
    {
        return DB::transaction(function () use ($generation, $providerStatus, $object): bool {
            /** @var Generation $locked */
            $locked = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isFinished()) {
                return true;
            }

            return match ($providerStatus) {
                GenerationStatus::Queued => true,
                GenerationStatus::Processing => $this->applyProcessing($locked, $object),
                GenerationStatus::Succeeded => $this->applySucceeded($locked, $object),
                GenerationStatus::Failed => $this->applyFailed($locked, $object),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function applyProcessing(Generation $locked, array $object): bool
    {
        $locked->forceFill([
            'status' => GenerationStatus::Processing,
            'started_at' => $locked->started_at ?? $this->timestamp($object, 'started_at') ?? now(),
        ])->save();

        return true;
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
}
