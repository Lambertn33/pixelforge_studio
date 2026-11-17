<?php

use App\Jobs\Pixelforge\ProcessWebhook;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\WebhookProcessor;

it('marks ping events as processed', function () {
    $event = WebhookEvent::factory()->ping()->create();

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($event->fresh()->processed_at)->not->toBeNull()
        ->and($event->fresh()->processing_error)->toBeNull();
});

it('skips events that were already processed', function () {
    $event = WebhookEvent::factory()->ping()->processed()->create([
        'processing_error' => null,
    ]);
    $processedAt = $event->processed_at;

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($event->fresh()->processed_at?->eq($processedAt))->toBeTrue();
});

it('leaves generation events unprocessed until later phases', function () {
    $event = WebhookEvent::factory()->succeeded()->create();

    (new ProcessWebhook($event->id))->handle(app(WebhookProcessor::class));

    expect($event->fresh()->processed_at)->toBeNull();
});
