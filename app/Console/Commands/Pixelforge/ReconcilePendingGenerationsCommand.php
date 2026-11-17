<?php

namespace App\Console\Commands\Pixelforge;

use App\Services\Pixelforge\Reconciler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pixelforge:reconcile-pending')]
#[Description('Poll Pixelforge for unfinished generations that may have missed webhooks')]
class ReconcilePendingGenerationsCommand extends Command
{
    public function handle(Reconciler $reconciler): int
    {
        $result = $reconciler->reconcile();

        if ($result['attempted'] === 0) {
            $this->info('No pending generations to reconcile.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Reconciled %d generation(s): %d applied, %d skipped, %d failed.',
            $result['attempted'],
            $result['applied'],
            $result['skipped'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
