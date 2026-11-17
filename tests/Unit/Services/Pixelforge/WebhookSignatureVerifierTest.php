<?php

use App\Exceptions\Pixelforge\InvalidWebhookSignatureException;
use App\Services\Pixelforge\WebhookSignatureVerifier;
use Illuminate\Support\Carbon;

function pixelforgeWebhookVerifier(
    string $secret = 'whsec_test_secret_for_signature_verifier',
    int $toleranceSeconds = 300,
): WebhookSignatureVerifier {
    return new WebhookSignatureVerifier($secret, $toleranceSeconds);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::createFromTimestamp(1_700_000_000));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('accepts a valid signature within the tolerance window', function () {
    [$body, $header] = signedPixelforgeWebhook(
        rawBody: '{"id":"evt_01TEST","type":"ping"}',
        timestamp: Carbon::now()->getTimestamp(),
    );

    $verifier = pixelforgeWebhookVerifier();

    expect($verifier->isValid($body, $header))->toBeTrue();

    $verifier->verify($body, $header);
});

it('rejects a missing signature header', function () {
    $verifier = pixelforgeWebhookVerifier();

    expect($verifier->isValid('{}', null))->toBeFalse();

    $verifier->verify('{}', null);
})->throws(InvalidWebhookSignatureException::class, 'Missing Pixelforge-Signature header.');

it('rejects an invalid signature', function () {
    $body = '{"id":"evt_01TEST","type":"ping"}';
    $header = 't='.Carbon::now()->getTimestamp().',v1='.str_repeat('a', 64);

    $verifier = pixelforgeWebhookVerifier();

    expect($verifier->isValid($body, $header))->toBeFalse();

    $verifier->verify($body, $header);
})->throws(InvalidWebhookSignatureException::class, 'Invalid Pixelforge-Signature.');

it('rejects a signature computed for a different body', function () {
    [, $header] = signedPixelforgeWebhook(
        rawBody: '{"id":"evt_01ORIGINAL"}',
        timestamp: Carbon::now()->getTimestamp(),
    );

    $verifier = pixelforgeWebhookVerifier();

    expect($verifier->isValid('{"id":"evt_01TAMPERED"}', $header))->toBeFalse();
});

it('rejects timestamps outside the tolerance window', function () {
    [$body, $header] = signedPixelforgeWebhook(
        rawBody: '{"id":"evt_01STALE"}',
        timestamp: Carbon::now()->getTimestamp() - 301,
    );

    $verifier = pixelforgeWebhookVerifier();

    expect($verifier->isValid($body, $header))->toBeFalse();

    $verifier->verify($body, $header);
})->throws(
    InvalidWebhookSignatureException::class,
    'Pixelforge-Signature timestamp is outside the allowed tolerance.',
);

it('rejects when the webhook secret is not configured', function () {
    $verifier = pixelforgeWebhookVerifier(secret: '');

    $verifier->verify('{}', 't=1,v1=abc');
})->throws(
    InvalidWebhookSignatureException::class,
    'Pixelforge webhook secret is not configured.',
);

it('rejects a malformed signature header', function () {
    $verifier = pixelforgeWebhookVerifier();

    $verifier->verify('{}', 'not-a-valid-header');
})->throws(InvalidWebhookSignatureException::class);
