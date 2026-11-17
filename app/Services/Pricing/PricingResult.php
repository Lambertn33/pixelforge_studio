<?php

namespace App\Services\Pricing;

/**
 * Exact money amounts are stored as nanodollars (1 nanodollar = $1e-9).
 */
final readonly class PricingResult
{
    /**
     * @param  array{
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int,
     *     total_tokens: int
     * }  $usage
     * @param  array{
     *     input_text_tokens: string,
     *     input_image_tokens: string,
     *     output_tokens: string,
     *     reasoning_tokens: string
     * }  $rates  Dollars per 1M tokens, as returned by Pixelforge
     */
    public function __construct(
        public int $providerCostNanodollars,
        public int $customerCostNanodollars,
        public array $usage,
        public array $rates,
        public string $currency,
        public bool $isEstimate,
    ) {}

    public function providerCostDollars(): string
    {
        return PricingCalculator::nanodollarsToDollarString($this->providerCostNanodollars);
    }

    public function customerCostDollars(): string
    {
        return PricingCalculator::nanodollarsToDollarString($this->customerCostNanodollars);
    }

    /**
     * Snapshot suitable for persisting on a generation.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'currency' => $this->currency,
            'unit' => 'per_1m_tokens',
            'is_estimate' => $this->isEstimate,
            'rates' => $this->rates,
            'usage' => $this->usage,
            'provider_cost_nanodollars' => $this->providerCostNanodollars,
            'customer_cost_nanodollars' => $this->customerCostNanodollars,
            'provider_cost_usd' => $this->providerCostDollars(),
            'customer_cost_usd' => $this->customerCostDollars(),
        ];
    }
}
