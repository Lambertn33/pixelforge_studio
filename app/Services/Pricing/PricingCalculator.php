<?php

namespace App\Services\Pricing;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use InvalidArgumentException;

/**
 * Exact Pixelforge pricing using integer nanodollars (1 nanodollar = $0.000000001).
 *
 * Provider cost = sum(tokens × rate_per_1M) / 1_000_000
 * Customer cost = provider cost × 5/4 (25% markup)
 *
 * Never uses PHP floating-point for money.
 */
final class PricingCalculator
{
    public const int NANODOLLARS_PER_DOLLAR = 1_000_000_000;

    public const int TOKENS_PER_MILLION = 1_000_000;

    /** Customer pays provider × 5/4 (25% markup). */
    public const int MARKUP_NUMERATOR = 5;

    public const int MARKUP_DENOMINATOR = 4;

    /**
     * Conservative upfront estimate from GET /pricing.
     *
     * Text tokens: ceil(chars / characters_per_text_token), then ceil(× (1 + variance)).
     * Image tokens: per_reference_image × count.
     * Output tokens: exact table from pricing payload.
     * Reasoning tokens: maximum for the selected quality.
     *
     * @param  array<string, mixed>  $pricing  Full GET /pricing payload
     */
    public function estimate(
        string $prompt,
        GenerationSize $size,
        GenerationQuality $quality,
        int $referenceImageCount,
        array $pricing,
    ): PricingResult {
        if ($referenceImageCount < 0) {
            throw new InvalidArgumentException('Reference image count cannot be negative.');
        }

        $rates = $this->extractRates($pricing);
        $currency = $this->extractCurrency($pricing);
        $tokenRules = $this->extractTokenRules($pricing);

        $charactersPerTextToken = $tokenRules['characters_per_text_token'];
        $varianceBasisPoints = $tokenRules['text_token_variance_basis_points'];
        $perReferenceImage = $tokenRules['per_reference_image'];

        $characterCount = mb_strlen($prompt, 'UTF-8');
        $baseTextTokens = $this->ceilDiv($characterCount, $charactersPerTextToken);
        $inputTextTokens = $this->ceilMulRatio($baseTextTokens, 10_000 + $varianceBasisPoints, 10_000);

        $inputImageTokens = $perReferenceImage * $referenceImageCount;
        $outputTokens = $this->outputTokens($tokenRules, $quality, $size);
        $reasoningTokens = $this->reasoningMaxTokens($tokenRules, $quality);

        return $this->fromUsageAndRates(
            usage: [
                'input_text_tokens' => $inputTextTokens,
                'input_image_tokens' => $inputImageTokens,
                'output_tokens' => $outputTokens,
                'reasoning_tokens' => $reasoningTokens,
                'total_tokens' => $inputTextTokens + $inputImageTokens + $outputTokens + $reasoningTokens,
            ],
            rates: $rates,
            currency: $currency,
            isEstimate: true,
        );
    }

    /**
     * Actual pricing for a succeeded generation using frozen rates on the generation object.
     *
     * @param  array{
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int,
     *     total_tokens?: int
     * }  $usage
     * @param  array{
     *     input_text_tokens: string,
     *     input_image_tokens: string,
     *     output_tokens: string,
     *     reasoning_tokens: string
     * }  $rates
     */
    public function actual(array $usage, array $rates, string $currency = 'usd'): PricingResult
    {
        $inputTextTokens = $this->requireNonNegativeInt($usage, 'input_text_tokens');
        $inputImageTokens = $this->requireNonNegativeInt($usage, 'input_image_tokens');
        $outputTokens = $this->requireNonNegativeInt($usage, 'output_tokens');
        $reasoningTokens = $this->requireNonNegativeInt($usage, 'reasoning_tokens');

        return $this->fromUsageAndRates(
            usage: [
                'input_text_tokens' => $inputTextTokens,
                'input_image_tokens' => $inputImageTokens,
                'output_tokens' => $outputTokens,
                'reasoning_tokens' => $reasoningTokens,
                'total_tokens' => $usage['total_tokens']
                    ?? ($inputTextTokens + $inputImageTokens + $outputTokens + $reasoningTokens),
            ],
            rates: $this->normalizeRates($rates),
            currency: $currency,
            isEstimate: false,
        );
    }

    /**
     * Failed generations are never billed.
     */
    public function zero(string $currency = 'usd'): PricingResult
    {
        return new PricingResult(
            providerCostNanodollars: 0,
            customerCostNanodollars: 0,
            usage: [
                'input_text_tokens' => 0,
                'input_image_tokens' => 0,
                'output_tokens' => 0,
                'reasoning_tokens' => 0,
                'total_tokens' => 0,
            ],
            rates: [
                'input_text_tokens' => '0.00',
                'input_image_tokens' => '0.00',
                'output_tokens' => '0.00',
                'reasoning_tokens' => '0.00',
            ],
            currency: $currency,
            isEstimate: false,
        );
    }

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
     * }  $rates
     */
    public function fromUsageAndRates(
        array $usage,
        array $rates,
        string $currency,
        bool $isEstimate,
    ): PricingResult {
        $providerCostNanodollars =
            $this->tokenCostNanodollars($usage['input_text_tokens'], $rates['input_text_tokens'])
            + $this->tokenCostNanodollars($usage['input_image_tokens'], $rates['input_image_tokens'])
            + $this->tokenCostNanodollars($usage['output_tokens'], $rates['output_tokens'])
            + $this->tokenCostNanodollars($usage['reasoning_tokens'], $rates['reasoning_tokens']);

        return new PricingResult(
            providerCostNanodollars: $providerCostNanodollars,
            customerCostNanodollars: $this->applyMarkup($providerCostNanodollars),
            usage: $usage,
            rates: $rates,
            currency: $currency,
            isEstimate: $isEstimate,
        );
    }

    /**
     * Convert an exact nanodollar amount to a nine-decimal dollar string.
     */
    public static function nanodollarsToDollarString(int $nanodollars): string
    {
        $negative = $nanodollars < 0;
        $absolute = abs($nanodollars);
        $dollars = intdiv($absolute, self::NANODOLLARS_PER_DOLLAR);
        $fraction = $absolute % self::NANODOLLARS_PER_DOLLAR;
        $formatted = sprintf('%d.%09d', $dollars, $fraction);

        return $negative ? '-'.$formatted : $formatted;
    }

    /**
     * Parse a decimal dollar string into nanodollars without floating point.
     */
    public static function dollarStringToNanodollars(string $amount): int
    {
        $amount = trim($amount);

        if ($amount === '' || ! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $amount, $matches)) {
            throw new InvalidArgumentException("Invalid dollar amount [{$amount}].");
        }

        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > 9) {
            throw new InvalidArgumentException("Dollar amount [{$amount}] has more than 9 decimal places.");
        }

        $nanodollars = ((int) $matches[2] * self::NANODOLLARS_PER_DOLLAR) + (int) str_pad($fraction, 9, '0');

        return $matches[1] === '-' ? -$nanodollars : $nanodollars;
    }

    /**
     * tokens × rate_per_1M / 1_000_000, in nanodollars.
     */
    public function tokenCostNanodollars(int $tokens, string $ratePerMillionDollars): int
    {
        if ($tokens < 0) {
            throw new InvalidArgumentException('Token count cannot be negative.');
        }

        if ($tokens === 0) {
            return 0;
        }

        $rateNanodollars = self::dollarStringToNanodollars($ratePerMillionDollars);
        $numerator = $tokens * $rateNanodollars;

        if ($numerator % self::TOKENS_PER_MILLION !== 0) {
            throw new InvalidArgumentException(
                "Token cost for {$tokens} tokens at {$ratePerMillionDollars}/1M is not exact in nanodollars.",
            );
        }

        return intdiv($numerator, self::TOKENS_PER_MILLION);
    }

    public function applyMarkup(int $providerCostNanodollars): int
    {
        $numerator = $providerCostNanodollars * self::MARKUP_NUMERATOR;

        if ($numerator % self::MARKUP_DENOMINATOR !== 0) {
            throw new InvalidArgumentException(
                "Customer markup is not exact in nanodollars for provider cost {$providerCostNanodollars}.",
            );
        }

        return intdiv($numerator, self::MARKUP_DENOMINATOR);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array{
     *     input_text_tokens: string,
     *     input_image_tokens: string,
     *     output_tokens: string,
     *     reasoning_tokens: string
     * }
     */
    private function extractRates(array $pricing): array
    {
        if (! isset($pricing['rates']) || ! is_array($pricing['rates'])) {
            throw new InvalidArgumentException('Pricing payload is missing rates.');
        }

        return $this->normalizeRates($pricing['rates']);
    }

    /**
     * @param  array<string, mixed>  $rates
     * @return array{
     *     input_text_tokens: string,
     *     input_image_tokens: string,
     *     output_tokens: string,
     *     reasoning_tokens: string
     * }
     */
    private function normalizeRates(array $rates): array
    {
        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $key) {
            if (! isset($rates[$key]) || ! is_string($rates[$key])) {
                throw new InvalidArgumentException("Pricing rates are missing string value for [{$key}].");
            }
        }

        return [
            'input_text_tokens' => $rates['input_text_tokens'],
            'input_image_tokens' => $rates['input_image_tokens'],
            'output_tokens' => $rates['output_tokens'],
            'reasoning_tokens' => $rates['reasoning_tokens'],
        ];
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    private function extractCurrency(array $pricing): string
    {
        $currency = $pricing['currency'] ?? 'usd';

        if (! is_string($currency) || $currency === '') {
            throw new InvalidArgumentException('Pricing payload has an invalid currency.');
        }

        return $currency;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array{
     *     characters_per_text_token: int,
     *     text_token_variance_basis_points: int,
     *     per_reference_image: int,
     *     output: array<string, array<string, int>>,
     *     reasoning: array<string, array{min: int, max: int}>
     * }
     */
    private function extractTokenRules(array $pricing): array
    {
        if (! isset($pricing['tokens']) || ! is_array($pricing['tokens'])) {
            throw new InvalidArgumentException('Pricing payload is missing tokens rules.');
        }

        $tokens = $pricing['tokens'];

        $charactersPerTextToken = $tokens['characters_per_text_token'] ?? null;
        $variance = $tokens['text_token_variance'] ?? null;
        $perReferenceImage = $tokens['per_reference_image'] ?? null;
        $output = $tokens['output'] ?? null;
        $reasoning = $tokens['reasoning'] ?? null;

        if (! is_int($charactersPerTextToken) || $charactersPerTextToken < 1) {
            throw new InvalidArgumentException('Invalid characters_per_text_token in pricing payload.');
        }

        if (! is_int($perReferenceImage) || $perReferenceImage < 0) {
            throw new InvalidArgumentException('Invalid per_reference_image in pricing payload.');
        }

        if (! is_array($output) || ! is_array($reasoning)) {
            throw new InvalidArgumentException('Pricing payload is missing output or reasoning tables.');
        }

        // 0.15 → 1500 basis points of 10000 (percent × 100).
        if (! is_float($variance) && ! is_int($variance)) {
            throw new InvalidArgumentException('Invalid text_token_variance in pricing payload.');
        }

        $varianceBasisPoints = (int) round(((float) $variance) * 10_000);

        if ($varianceBasisPoints < 0) {
            throw new InvalidArgumentException('text_token_variance cannot be negative.');
        }

        /** @var array<string, array<string, int>> $output */
        /** @var array<string, array{min: int, max: int}> $reasoning */
        return [
            'characters_per_text_token' => $charactersPerTextToken,
            'text_token_variance_basis_points' => $varianceBasisPoints,
            'per_reference_image' => $perReferenceImage,
            'output' => $output,
            'reasoning' => $reasoning,
        ];
    }

    /**
     * @param  array{
     *     characters_per_text_token: int,
     *     text_token_variance_basis_points: int,
     *     per_reference_image: int,
     *     output: array<string, array<string, int>>,
     *     reasoning: array<string, array{min: int, max: int}>
     * }  $tokenRules
     */
    private function outputTokens(array $tokenRules, GenerationQuality $quality, GenerationSize $size): int
    {
        $value = $tokenRules['output'][$quality->value][$size->value] ?? null;

        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException(
                "Missing output token count for quality [{$quality->value}] and size [{$size->value}].",
            );
        }

        return $value;
    }

    /**
     * @param  array{
     *     characters_per_text_token: int,
     *     text_token_variance_basis_points: int,
     *     per_reference_image: int,
     *     output: array<string, array<string, int>>,
     *     reasoning: array<string, array{min: int, max: int}>
     * }  $tokenRules
     */
    private function reasoningMaxTokens(array $tokenRules, GenerationQuality $quality): int
    {
        $max = $tokenRules['reasoning'][$quality->value]['max'] ?? null;

        if (! is_int($max) || $max < 0) {
            throw new InvalidArgumentException(
                "Missing reasoning max for quality [{$quality->value}].",
            );
        }

        return $max;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function requireNonNegativeInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;

        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("Usage [{$key}] must be a non-negative integer.");
        }

        return $value;
    }

    private function ceilDiv(int $numerator, int $denominator): int
    {
        if ($denominator < 1) {
            throw new InvalidArgumentException('Denominator must be positive.');
        }

        if ($numerator <= 0) {
            return 0;
        }

        return intdiv($numerator + $denominator - 1, $denominator);
    }

    /**
     * ceil(value × numerator / denominator) using integer arithmetic.
     */
    private function ceilMulRatio(int $value, int $numerator, int $denominator): int
    {
        if ($denominator < 1 || $numerator < 0) {
            throw new InvalidArgumentException('Invalid ratio for ceil multiplication.');
        }

        if ($value <= 0 || $numerator === 0) {
            return 0;
        }

        return intdiv(($value * $numerator) + $denominator - 1, $denominator);
    }
}
