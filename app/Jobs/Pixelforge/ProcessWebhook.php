<?php

namespace App\Jobs\Pixelforge;

use App\Services\Pixelforge\WebhookProcessor;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWebhook implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $webhookEventId) {}

    public function uniqueId(): string
    {
        return (string) $this->webhookEventId;
    }

    public function handle(WebhookProcessor $processor): void
    {
        $processor->process($this->webhookEventId);
    }
}
