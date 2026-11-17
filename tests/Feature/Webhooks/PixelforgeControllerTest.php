<?php

use Illuminate\Support\Carbon;

beforeEach(function () {
    config([
        'pixelforge.webhook_secret' => 'whsec_test_secret_for_signature_verifier',
        'pixelforge.webhook_tolerance_seconds' => 300,
    ]);

    Carbon::setTestNow(Carbon::createFromTimestamp(1_700_000_000));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('accepts a signed webhook without authentication', function () {
    $payload = json_encode([
        'id' => 'evt_01TESTPING000000000000001',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => '2026-11-17T10:00:00Z',
        'data' => ['object' => ['ok' => true]],
    ], JSON_THROW_ON_ERROR);

    [$body, $header] = signedPixelforgeWebhook($payload, Carbon::now()->getTimestamp());

    $response = $this->call(
        'POST',
        '/api/webhooks/pixelforge',
        content: $body,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $header,
            'HTTP_PIXELFORGE_EVENT_ID' => 'evt_01TESTPING000000000000001',
            'HTTP_PIXELFORGE_EVENT_TYPE' => 'ping',
        ],
    );

    $response->assertNoContent();
});

it('rejects a webhook with a missing signature', function () {
    $response = $this->postJson('/api/webhooks/pixelforge', [
        'id' => 'evt_01TESTPING000000000000001',
        'type' => 'ping',
    ]);

    $response->assertBadRequest()
        ->assertSee('Invalid Pixelforge signature.');
});

it('rejects a webhook with an invalid signature', function () {
    $payload = '{"id":"evt_01TESTPING000000000000001","type":"ping"}';

    $response = $this->call(
        'POST',
        '/api/webhooks/pixelforge',
        content: $payload,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => 't='.Carbon::now()->getTimestamp().',v1='.str_repeat('0', 64),
        ],
    );

    $response->assertBadRequest()
        ->assertSee('Invalid Pixelforge signature.');
});

it('does not require a csrf token', function () {
    $payload = '{"id":"evt_01TESTPING000000000000001","type":"ping"}';
    [$body, $header] = signedPixelforgeWebhook($payload, Carbon::now()->getTimestamp());

    $response = $this->call(
        'POST',
        '/api/webhooks/pixelforge',
        content: $body,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $header,
            'HTTP_ACCEPT' => 'application/json',
        ],
    );

    $response->assertNoContent();
});
