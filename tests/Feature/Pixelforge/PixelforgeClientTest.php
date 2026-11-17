<?php

use App\Exceptions\Pixelforge\PixelforgeRequestException;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'pixelforge.base_url' => 'https://pixelforge.test/v1',
        'pixelforge.api_key' => 'test-key',
        'pixelforge.http.connect_timeout' => 1,
        'pixelforge.http.timeout' => 1,
        'pixelforge.http.retry.times' => 3,
        'pixelforge.http.retry.sleep_milliseconds' => 0,
    ]);

    Http::preventStrayRequests();
});

test('pricing returns the provider payload', function () {
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response([
            'currency' => 'usd',
            'rates' => ['low' => 1],
        ]),
    ]);

    $payload = app(PixelforgeClient::class)->pricing();

    expect($payload)->toMatchArray([
        'currency' => 'usd',
        'rates' => ['low' => 1],
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->url() === 'https://pixelforge.test/v1/pricing');
});

test('createGeneration sends an idempotency key', function () {
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response([
            'id' => 'gen_123',
            'status' => 'queued',
        ], 201),
    ]);

    $payload = app(PixelforgeClient::class)->createGeneration(
        [
            'prompt' => 'A red fox',
            'size' => '1024x1024',
            'quality' => 'low',
            'metadata' => ['local_id' => 'uuid-1'],
        ],
        'idem-key-1',
    );

    expect($payload['id'])->toBe('gen_123');

    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', 'idem-key-1')
        && $request->method() === 'POST'
        && $request->url() === 'https://pixelforge.test/v1/generations');
});

test('maps permanent provider errors without retrying', function () {
    Http::fake([
        'pixelforge.test/v1/generations/*' => Http::response([
            'error' => [
                'code' => 'not_found',
                'message' => 'Generation not found',
            ],
        ], 404),
    ]);

    try {
        app(PixelforgeClient::class)->getGeneration('gen_missing');
        $this->fail('Expected PixelforgeRequestException');
    } catch (PixelforgeRequestException $exception) {
        expect($exception->status)->toBe(404)
            ->and($exception->errorCode)->toBe('not_found')
            ->and($exception->isNotFound())->toBeTrue()
            ->and($exception->isRetryable())->toBeFalse();
    }

    Http::assertSentCount(1);
});

test('retries transient provider failures then succeeds', function () {
    Http::fake([
        'pixelforge.test/v1/usage' => Http::sequence()
            ->push(['error' => ['message' => 'Overloaded']], 503)
            ->push(['error' => ['message' => 'Overloaded']], 503)
            ->push(['requests' => 3], 200),
    ]);

    $payload = app(PixelforgeClient::class)->usage();

    expect($payload)->toMatchArray(['requests' => 3]);

    Http::assertSentCount(3);
});

test('throws after exhausting retries for rate limits', function () {
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response([
            'error' => [
                'code' => 'rate_limited',
                'message' => 'Too many requests',
            ],
        ], 429, ['Retry-After' => '2']),
    ]);

    try {
        app(PixelforgeClient::class)->pricing();
        $this->fail('Expected PixelforgeRequestException');
    } catch (PixelforgeRequestException $exception) {
        expect($exception->isRateLimited())->toBeTrue()
            ->and($exception->retryAfterSeconds)->toBe(2)
            ->and($exception->isRetryable())->toBeTrue();
    }

    Http::assertSentCount(3);
});
