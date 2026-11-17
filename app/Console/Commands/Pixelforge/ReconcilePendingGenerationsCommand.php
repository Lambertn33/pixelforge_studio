<?php

namespace App\Console\Commands\Pixelforge;

use App\Services\Pixelforge\PendingGenerationFinder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pixelforge:reconcile-pending')]
#[Description('List unfinished Pixelforge generations that are eligible for reconciliation polling')]
class ReconcilePendingGenerationsCommand extends Command
{
    public function handle(PendingGenerationFinder $finder): int
    {
        $candidates = $finder->get();

        if ($candidates->isEmpty()) {
            $this->info('No pending generations to reconcile.');

            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} pending generation(s).");

        foreach ($candidates as $generation) {
            $this->line(sprintf(
                '%d %s %s submitted_at=%s',
                $generation->id,
                $generation->pixelforge_id,
                $generation->status->value,
                $generation->submitted_at?->toIso8601String() ?? 'null',
            ));
        }

        return self::SUCCESS;
    }
}
