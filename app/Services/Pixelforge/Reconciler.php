<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\Log;
use Throwable;

class Reconciler
{
    public function __construct(
        private PendingGenerationFinder $finder,
        private Client $client,
        private StatusApplier $applier,
    ) {}

    /**
     * @return array{attempted: int, applied: int, skipped: int, failed: int}
     */
    public function reconcile(): array
    {
        $candidates = $this->finder->get();

        $applied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($candidates as $generation) {
            try {
                if ($this->reconcileOne($generation)) {
                    $applied++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('Pixelforge reconciliation failed for generation.', [
                    'generation_id' => $generation->id,
                    'pixelforge_id' => $generation->pixelforge_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'attempted' => $candidates->count(),
            'applied' => $applied,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    private function reconcileOne(Generation $generation): bool
    {
        if (! is_string($generation->pixelforge_id) || $generation->pixelforge_id === '') {
            return false;
        }

        $object = $this->client->getGeneration($generation->pixelforge_id);
        $statusValue = $object['status'] ?? null;

        if (! is_string($statusValue)) {
            return false;
        }

        $providerStatus = GenerationStatus::tryFrom($statusValue);

        if ($providerStatus === null) {
            return false;
        }

        return $this->applier->apply($generation, $providerStatus, $object);
    }
}
