<?php

use App\Enums\PixelforgeEventType;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

test('a webhook event can be stored without a generation and later associated', function () {
    $event = WebhookEvent::factory()->create([
        'event_id' => 'evt_'.Str::upper(Str::random(26)),
        'event_type' => PixelforgeEventType::GenerationProcessing,
        'generation_id' => null,
    ]);

    expect($event->generation_id)->toBeNull()
        ->and($event->event_type)->toBe(PixelforgeEventType::GenerationProcessing)
        ->and($event->processed_at)->toBeNull()
        ->and($event->payload)->toBeArray();

    $generation = Generation::factory()->processing()->create();

    $event->update(['generation_id' => $generation->id]);

    expect($event->fresh()->generation->is($generation))->toBeTrue()
        ->and($generation->webhookEvents)->toHaveCount(1);
});

test('event_id must be unique for idempotent webhook delivery', function () {
    $eventId = 'evt_'.Str::upper(Str::random(26));

    WebhookEvent::factory()->create(['event_id' => $eventId]);

    WebhookEvent::factory()->create(['event_id' => $eventId]);
})->throws(UniqueConstraintViolationException::class);

test('webhook events can be marked processed or failed', function () {
    $event = WebhookEvent::factory()->create();

    $event->markProcessed();

    expect($event->fresh()->processed_at)->not->toBeNull()
        ->and($event->fresh()->processing_error)->toBeNull();

    $failed = WebhookEvent::factory()->create();
    $failed->markFailed('queue dispatch failed');

    expect($failed->fresh()->processed_at)->not->toBeNull()
        ->and($failed->fresh()->processing_error)->toBe('queue dispatch failed');
});
