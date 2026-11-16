<?php

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

test('a purchase credit transaction belongs to a user and preserves exact money', function () {
    $user = User::factory()->create();

    $transaction = CreditTransaction::factory()->purchase('25.500000000')->create([
        'user_id' => $user->id,
        'balance_after' => '25.500000000',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    expect($transaction->user->is($user))->toBeTrue()
        ->and($transaction->type)->toBe(CreditTransactionType::Purchase)
        ->and($transaction->amount)->toBe('25.500000000')
        ->and($transaction->isCredit())->toBeTrue()
        ->and($transaction->isDebit())->toBeFalse()
        ->and($user->creditTransactions)->toHaveCount(1);
});

test('a generation can only be charged once via unique generation type constraint', function () {
    $generation = Generation::factory()->succeeded()->create();

    CreditTransaction::factory()
        ->generationCharge($generation, '-0.057656250')
        ->create(['balance_after' => '9.942343750']);

    CreditTransaction::factory()
        ->generationCharge($generation, '-0.057656250')
        ->create(['balance_after' => '9.884687500']);
})->throws(UniqueConstraintViolationException::class);

test('idempotency_key must be unique', function () {
    $key = (string) Str::uuid();

    CreditTransaction::factory()->create(['idempotency_key' => $key]);

    CreditTransaction::factory()->create(['idempotency_key' => $key]);
})->throws(UniqueConstraintViolationException::class);
