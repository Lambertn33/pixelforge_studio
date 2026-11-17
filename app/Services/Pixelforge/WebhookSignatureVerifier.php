<?php

namespace App\Services\Pixelforge;

use App\Exceptions\Pixelforge\InvalidWebhookSignatureException;
use Illuminate\Support\Carbon;

/**
 * Verifies Pixelforge webhook signatures against the raw request body.
 *
 * Header format: Pixelforge-Signature: t=UNIX_TIMESTAMP,v1=HEX_HMAC_SHA256
 * Signed payload: "{timestamp}.{raw_body}"
 */
class WebhookSignatureVerifier
{
    public function __construct(
        private readonly ?string $secret = null,
        private readonly ?int $toleranceSeconds = null,
    ) {}

    /**
     * @throws InvalidWebhookSignatureException
     */
    public function verify(string $rawBody, ?string $signatureHeader): void
    {
        $secret = $this->secret ?? (string) config('pixelforge.webhook_secret', '');

        if ($secret === '') {
            throw new InvalidWebhookSignatureException('Pixelforge webhook secret is not configured.');
        }

        if ($signatureHeader === null || $signatureHeader === '') {
            throw new InvalidWebhookSignatureException('Missing Pixelforge-Signature header.');
        }

        $parts = $this->parseSignatureHeader($signatureHeader);
        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if ($timestamp === null || $timestamp === '' || ! ctype_digit($timestamp)) {
            throw new InvalidWebhookSignatureException('Invalid Pixelforge-Signature timestamp.');
        }

        if ($signature === null || $signature === '') {
            throw new InvalidWebhookSignatureException('Invalid Pixelforge-Signature value.');
        }

        $timestampInt = (int) $timestamp;
        $toleranceSeconds = $this->toleranceSeconds ?? (int) config('pixelforge.webhook_tolerance_seconds', 300);

        if (abs(Carbon::now()->getTimestamp() - $timestampInt) > $toleranceSeconds) {
            throw new InvalidWebhookSignatureException('Pixelforge-Signature timestamp is outside the allowed tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignatureException('Invalid Pixelforge-Signature.');
        }
    }

    public function isValid(string $rawBody, ?string $signatureHeader): bool
    {
        try {
            $this->verify($rawBody, $signatureHeader);

            return true;
        } catch (InvalidWebhookSignatureException) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || ! str_contains($segment, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $segment, 2);
            $parts[trim($key)] = trim($value);
        }

        return $parts;
    }
}
