<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'pixelforge.base_url' => 'https://pixelforge.test/v1',
        'pixelforge.api_key' => 'test-key',
        'pixelforge.http.connect_timeout' => 1,
        'pixelforge.http.timeout' => 1,
        'pixelforge.http.retry.times' => 1,
        'pixelforge.http.retry.sleep_milliseconds' => 0,
        'pixelforge.reconciliation.min_age_seconds' => 60,
        'pixelforge.reconciliation.batch_size' => 50,
    ]);

    Http::preventStrayRequests();
});

it('reconciles eligible pending generations via the artisan command', function () {
    $this->freezeTime();

    $generation = Generation::factory()->processing()->create([
        'pixelforge_id' => 'gen_01COMMANDRECONCILE000000001',
        'submitted_at' => now()->subMinutes(5),
    ]);

    Http::fake([
        'pixelforge.test/v1/generations/gen_01COMMANDRECONCILE000000001' => Http::response([
            'id' => 'gen_01COMMANDRECONCILE000000001',
            'object' => 'generation',
            'status' => 'failed',
            'error' => [
                'code' => 'generation_failed',
                'message' => 'Failed.',
            ],
            'completed_at' => '2026-11-17T10:00:30Z',
        ]),
    ]);

    $exitCode = Artisan::call('pixelforge:reconcile-pending');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Reconciled 1 generation(s): 1 applied, 0 skipped, 0 failed.')
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Failed);
});

it('reports when nothing is pending', function () {
    $exitCode = Artisan::call('pixelforge:reconcile-pending');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No pending generations to reconcile.');
});

it('registers the reconcile command on the schedule every minute without overlapping', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())->first(
        fn ($scheduledEvent): bool => str_contains($scheduledEvent->command ?? '', 'pixelforge:reconcile-pending'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
