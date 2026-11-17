<?php

namespace App\Exceptions\Pixelforge;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

class PixelforgeRequestException extends Exception
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly array $body = [],
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromRequestException(RequestException $exception): self
    {
        $response = $exception->response;

        return self::fromResponse($response, $exception);
    }

    public static function fromResponse(Response $response, ?Throwable $previous = null): self
    {
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $errorCode = isset($body['error']['code']) && is_string($body['error']['code'])
            ? $body['error']['code']
            : (isset($body['code']) && is_string($body['code']) ? $body['code'] : null);
        $message = isset($body['error']['message']) && is_string($body['error']['message'])
            ? $body['error']['message']
            : (isset($body['message']) && is_string($body['message'])
                ? $body['message']
                : 'Pixelforge request failed with status '.$response->status());

        return new self(
            message: $message,
            status: $response->status(),
            errorCode: $errorCode,
            body: $body,
            retryAfterSeconds: self::parseRetryAfter($response->header('Retry-After')),
            previous: $previous,
        );
    }

    public static function fromConnectionException(ConnectionException $exception): self
    {
        return new self(
            message: 'Pixelforge connection failed: '.$exception->getMessage(),
            status: 0,
            previous: $exception,
        );
    }

    public function isUnauthorized(): bool
    {
        return $this->status === 401;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    public function isValidationError(): bool
    {
        return in_array($this->status, [400, 422], true);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    public function isRetryable(): bool
    {
        return $this->status === 0
            || $this->status === 429
            || $this->status >= 500;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'pixelforge_status' => $this->status,
            'pixelforge_error_code' => $this->errorCode,
            'pixelforge_retry_after' => $this->retryAfterSeconds,
        ];
    }

    private static function parseRetryAfter(?string $retryAfter): ?int
    {
        if ($retryAfter === null || $retryAfter === '') {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return max(0, (int) $retryAfter);
        }

        $timestamp = strtotime($retryAfter);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
