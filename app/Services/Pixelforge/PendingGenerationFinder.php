<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Database\Eloquent\Collection;

class PendingGenerationFinder
{
    /**
     * @return Collection<int, Generation>
     */
    public function get(): Collection
    {
        $minAgeSeconds = max(0, (int) config('pixelforge.reconciliation.min_age_seconds'));
        $batchSize = max(1, (int) config('pixelforge.reconciliation.batch_size'));

        return Generation::query()
            ->whereIn('status', [
                GenerationStatus::Queued,
                GenerationStatus::Processing,
            ])
            ->whereNotNull('pixelforge_id')
            ->where('submitted_at', '<=', now()->subSeconds($minAgeSeconds))
            ->orderBy('submitted_at')
            ->limit($batchSize)
            ->get();
    }
}
