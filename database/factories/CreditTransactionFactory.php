<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'generation_id' => null,
            'type' => CreditTransactionType::Purchase,
            'amount' => '10.000000000',
            'balance_after' => '10.000000000',
            'currency' => 'usd',
            'idempotency_key' => (string) Str::uuid(),
            'description' => 'Fake credit purchase',
            'metadata' => [],
        ];
    }

    public function purchase(string $amount = '10.000000000'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CreditTransactionType::Purchase,
            'generation_id' => null,
            'amount' => $amount,
            'description' => 'Fake credit purchase',
        ]);
    }

    public function generationCharge(Generation $generation, string $amount = '-0.057656250'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $generation->user_id,
            'generation_id' => $generation->id,
            'type' => CreditTransactionType::GenerationCharge,
            'amount' => $amount,
            'idempotency_key' => 'generation_charge:'.$generation->id,
            'description' => 'Charge for successful generation',
        ]);
    }

    public function refund(Generation $generation, string $amount = '0.057656250'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $generation->user_id,
            'generation_id' => $generation->id,
            'type' => CreditTransactionType::Refund,
            'amount' => $amount,
            'idempotency_key' => 'refund:'.$generation->id,
            'description' => 'Refund for generation',
        ]);
    }
}
