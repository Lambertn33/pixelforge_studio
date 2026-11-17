<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

it('lists eligible pending generations', function () {
    config([
        'pixelforge.reconciliation.min_age_seconds' => 60,
        'pixelforge.reconciliation.batch_size' => 50,
    ]);

    $this->freezeTime();

    $generation = Generation::factory()->create([
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => 'gen_01COMMANDLIST00000000000001',
        'submitted_at' => now()->subMinutes(5),
    ]);

    Artisan::call('pixelforge:reconcile-pending');

    $output = Artisan::output();

    expect($output)->toContain('Found 1 pending generation(s).')
        ->and($output)->toContain((string) $generation->id)
        ->and($output)->toContain('gen_01COMMANDLIST00000000000001');
});

it('reports when nothing is pending', function () {
    Artisan::call('pixelforge:reconcile-pending');

    expect(Artisan::output())->toContain('No pending generations to reconcile.');
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
