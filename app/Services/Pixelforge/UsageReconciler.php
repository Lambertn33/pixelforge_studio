<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Carbon;

class UsageReconciler
{
    public function __construct(private Client $client) {}

    /**
     * Compare Pixelforge GET /usage (API-key account truth) to local succeeded costs.
     *
     * Generations are billed on the UTC day they completed.
     *
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     provider: array{
     *         generations: int,
     *         input_text_tokens: int,
     *         input_image_tokens: int,
     *         output_tokens: int,
     *         reasoning_tokens: int,
     *         cost_usd: string
     *     },
     *     local: array{
     *         generations: int,
     *         input_text_tokens: int,
     *         input_image_tokens: int,
     *         output_tokens: int,
     *         reasoning_tokens: int,
     *         cost_usd: string,
     *         customer_cost_usd: string
     *     },
     *     difference_usd: string,
     *     matched: bool,
     *     days: list<array{
     *         date: string,
     *         provider_generations: int,
     *         local_generations: int,
     *         provider_cost_usd: string,
     *         local_cost_usd: string,
     *         difference_usd: string,
     *         matched: bool
     *     }>
     * }
     */
    public function reconcile(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $end = ($endDate ?? now('UTC'))->copy()->utc()->startOfDay();
        $start = ($startDate ?? $end->copy()->subDays(29))->copy()->utc()->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $startKey = $start->toDateString();
        $endKey = $end->toDateString();

        $provider = $this->client->usage([
            'start_date' => $startKey,
            'end_date' => $endKey,
        ]);

        $localByDate = $this->localDailyTotals($start, $end);
        $providerDays = is_array($provider['data'] ?? null) ? $provider['data'] : [];

        $days = [];
        $localTotal = $this->emptyTotals();

        foreach ($providerDays as $day) {
            if (! is_array($day) || ! isset($day['date']) || ! is_string($day['date'])) {
                continue;
            }

            $date = $day['date'];
            $local = $localByDate[$date] ?? $this->emptyTotals();
            $providerCost = $this->moneyString($day['cost_usd'] ?? '0.000000000');
            $localCost = $local['cost_usd'];
            $difference = bcsub($providerCost, $localCost, 9);

            $days[] = [
                'date' => $date,
                'provider_generations' => (int) ($day['generations'] ?? 0),
                'local_generations' => $local['generations'],
                'provider_cost_usd' => $providerCost,
                'local_cost_usd' => $localCost,
                'difference_usd' => $difference,
                'matched' => bccomp($difference, '0.000000000', 9) === 0
                    && (int) ($day['generations'] ?? 0) === $local['generations'],
            ];

            $localTotal = $this->addTotals($localTotal, $local);
        }

        $providerTotal = is_array($provider['total'] ?? null) ? $provider['total'] : [];
        $providerCostTotal = $this->moneyString($providerTotal['cost_usd'] ?? '0.000000000');
        $differenceTotal = bcsub($providerCostTotal, $localTotal['cost_usd'], 9);

        return [
            'start_date' => is_string($provider['start_date'] ?? null) ? $provider['start_date'] : $startKey,
            'end_date' => is_string($provider['end_date'] ?? null) ? $provider['end_date'] : $endKey,
            'provider' => [
                'generations' => (int) ($providerTotal['generations'] ?? 0),
                'input_text_tokens' => (int) ($providerTotal['input_text_tokens'] ?? 0),
                'input_image_tokens' => (int) ($providerTotal['input_image_tokens'] ?? 0),
                'output_tokens' => (int) ($providerTotal['output_tokens'] ?? 0),
                'reasoning_tokens' => (int) ($providerTotal['reasoning_tokens'] ?? 0),
                'cost_usd' => $providerCostTotal,
            ],
            'local' => $localTotal,
            'difference_usd' => $differenceTotal,
            'matched' => bccomp($differenceTotal, '0.000000000', 9) === 0
                && (int) ($providerTotal['generations'] ?? 0) === $localTotal['generations'],
            'days' => $days,
        ];
    }

    /**
     * @return array<string, array{
     *     generations: int,
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int,
     *     cost_usd: string,
     *     customer_cost_usd: string
     * }>
     */
    private function localDailyTotals(Carbon $start, Carbon $end): array
    {
        $rows = Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $start->copy()->startOfDay())
            ->where('completed_at', '<=', $end->copy()->endOfDay())
            ->get([
                'completed_at',
                'usage',
                'actual_provider_cost',
                'actual_customer_cost',
            ]);

        $byDate = [];

        foreach ($rows as $generation) {
            $date = $generation->completed_at?->copy()->utc()->toDateString();

            if ($date === null) {
                continue;
            }

            $current = $byDate[$date] ?? $this->emptyTotals();
            $usage = is_array($generation->usage) ? $generation->usage : [];

            $current['generations']++;
            $current['input_text_tokens'] += (int) ($usage['input_text_tokens'] ?? 0);
            $current['input_image_tokens'] += (int) ($usage['input_image_tokens'] ?? 0);
            $current['output_tokens'] += (int) ($usage['output_tokens'] ?? 0);
            $current['reasoning_tokens'] += (int) ($usage['reasoning_tokens'] ?? 0);
            $current['cost_usd'] = bcadd(
                $current['cost_usd'],
                $this->moneyString($generation->actual_provider_cost),
                9,
            );
            $current['customer_cost_usd'] = bcadd(
                $current['customer_cost_usd'],
                $this->moneyString($generation->actual_customer_cost),
                9,
            );

            $byDate[$date] = $current;
        }

        return $byDate;
    }

    /**
     * @return array{
     *     generations: int,
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int,
     *     cost_usd: string,
     *     customer_cost_usd: string
     * }
     */
    private function emptyTotals(): array
    {
        return [
            'generations' => 0,
            'input_text_tokens' => 0,
            'input_image_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'cost_usd' => '0.000000000',
            'customer_cost_usd' => '0.000000000',
        ];
    }

    /**
     * @param  array{
     *     generations: int,
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int,
     *     cost_usd: string,
     *     customer_cost_usd: string
     * }  $left
     * @param  array{
     *     generations: int,
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int,
     *     cost_usd: string,
     *     customer_cost_usd: string
     * }  $right
     * @return array{
     *     generations: int,
     *     input_text_tokens: int,
     *     input_image_tokens: int,
     *     output_tokens: int,
     *     reasoning_tokens: int,
     *     cost_usd: string,
     *     customer_cost_usd: string
     * }
     */
    private function addTotals(array $left, array $right): array
    {
        return [
            'generations' => $left['generations'] + $right['generations'],
            'input_text_tokens' => $left['input_text_tokens'] + $right['input_text_tokens'],
            'input_image_tokens' => $left['input_image_tokens'] + $right['input_image_tokens'],
            'output_tokens' => $left['output_tokens'] + $right['output_tokens'],
            'reasoning_tokens' => $left['reasoning_tokens'] + $right['reasoning_tokens'],
            'cost_usd' => bcadd($left['cost_usd'], $right['cost_usd'], 9),
            'customer_cost_usd' => bcadd($left['customer_cost_usd'], $right['customer_cost_usd'], 9),
        ];
    }

    private function moneyString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.000000000';
        }

        return bcadd((string) $value, '0', 9);
    }
}
