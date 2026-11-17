<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PendingGenerationFinder;

beforeEach(function () {
    config([
        'pixelforge.reconciliation.min_age_seconds' => 60,
        'pixelforge.reconciliation.batch_size' => 50,
    ]);

    $this->freezeTime();
});

it('selects unfinished generations with a provider id older than the age threshold', function () {
    $eligibleQueued = Generation::factory()->create([
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => 'gen_01ELIGIBLEQUEUED00000000001',
        'submitted_at' => now()->subSeconds(90),
    ]);

    $eligibleProcessing = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01ELIGIBLEPROCESSING0000001',
        'submitted_at' => now()->subSeconds(120),
    ]);

    $ids = app(PendingGenerationFinder::class)->get()->pluck('id')->all();

    expect($ids)->toBe([$eligibleProcessing->id, $eligibleQueued->id]);
});

it('excludes finished generations', function () {
    Generation::factory()->succeeded()->create([
        'submitted_at' => now()->subMinutes(10),
    ]);

    Generation::factory()->failed()->create([
        'submitted_at' => now()->subMinutes(10),
    ]);

    expect(app(PendingGenerationFinder::class)->get())->toBeEmpty();
});

it('excludes generations without a pixelforge id', function () {
    Generation::factory()->create([
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => null,
        'submitted_at' => now()->subMinutes(10),
    ]);

    expect(app(PendingGenerationFinder::class)->get())->toBeEmpty();
});

it('excludes generations younger than the age threshold', function () {
    Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01TOOYOUNG00000000000000001',
        'submitted_at' => now()->subSeconds(30),
    ]);

    expect(app(PendingGenerationFinder::class)->get())->toBeEmpty();
});

it('limits results to the configured batch size and returns oldest first', function () {
    config(['pixelforge.reconciliation.batch_size' => 2]);

    $oldest = Generation::factory()->create([
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => 'gen_01OLDEST0000000000000000001',
        'submitted_at' => now()->subMinutes(5),
    ]);

    $middle = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01MIDDLE0000000000000000001',
        'submitted_at' => now()->subMinutes(3),
    ]);

    Generation::factory()->create([
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => 'gen_01NEWEST0000000000000000001',
        'submitted_at' => now()->subMinutes(2),
    ]);

    $ids = app(PendingGenerationFinder::class)->get()->pluck('id')->all();

    expect($ids)->toBe([$oldest->id, $middle->id]);
});
