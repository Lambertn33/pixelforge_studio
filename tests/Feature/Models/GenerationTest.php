<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

test('a generation belongs to a user and casts enums and money', function () {
    $user = User::factory()->create();

    $generation = Generation::factory()->create([
        'user_id' => $user->id,
        'client_request_id' => (string) Str::uuid(),
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => GenerationSize::Landscape,
        'quality' => GenerationQuality::High,
        'status' => GenerationStatus::Queued,
        'estimated_provider_cost' => '0.373875000',
        'estimated_customer_cost' => '0.467343750',
        'metadata' => ['client_request_id' => 'local-uuid'],
    ]);

    expect($generation->user->is($user))->toBeTrue()
        ->and($generation->size)->toBe(GenerationSize::Landscape)
        ->and($generation->quality)->toBe(GenerationQuality::High)
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->isTerminal())->toBeFalse()
        ->and($generation->estimated_provider_cost)->toBe('0.373875000')
        ->and($generation->estimated_customer_cost)->toBe('0.467343750')
        ->and($user->generations)->toHaveCount(1);
});

test('succeeded and failed generations are terminal', function () {
    $succeeded = Generation::factory()->succeeded()->create();
    $failed = Generation::factory()->failed()->create();

    expect($succeeded->isTerminal())->toBeTrue()
        ->and($failed->isTerminal())->toBeTrue()
        ->and($failed->actual_customer_cost)->toBe('0.000000000');
});

test('client_request_id must be unique', function () {
    $clientRequestId = (string) Str::uuid();

    Generation::factory()->create(['client_request_id' => $clientRequestId]);

    Generation::factory()->create(['client_request_id' => $clientRequestId]);
})->throws(UniqueConstraintViolationException::class);
