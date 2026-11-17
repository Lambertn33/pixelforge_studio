<?php

namespace App\Services\Pixelforge;

use App\Enums\PixelforgeEventType;
use App\Models\WebhookEvent;

class WebhookProcessor
{
    public function process(int $webhookEventId): void
    {
        /** @var WebhookEvent|null $event */
        $event = WebhookEvent::query()->find($webhookEventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        if ($event->event_type === PixelforgeEventType::Ping) {
            $event->markProcessed();
        }

        // Generation event application lands in 6.5–6.7.
    }
}
