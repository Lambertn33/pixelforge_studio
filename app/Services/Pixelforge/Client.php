<?php

namespace App\Services\Pixelforge;

use App\Exceptions\Pixelforge\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException as HttpClientRequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class Client
{
    public function pricing(): array
    {
        return $this->get('/pricing');
    }

    public function createGeneration(array $payload, string $idempotencyKey): array
    {
        return $this->send(function () use ($payload, $idempotencyKey): Response {
            return $this->request(retry: true)
                ->withHeaders([
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->post('/generations', $payload);
        });
    }

    public function getGeneration(string $pixelforgeId): array
    {
        return $this->get("/generations/{$pixelforgeId}");
    }

    public function listGenerations(array $query = []): array
    {
        return $this->get('/generations', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function usage(array $query = []): array
    {
        return $this->get('/usage', $query);
    }

    protected function get(string $uri, array $query = []): array
    {
        return $this->send(function () use ($uri, $query): Response {
            return $this->request(retry: true)->get($uri, $query);
        });
    }

    protected function send(callable $callback): array
    {
        try {
            $response = $callback();
        } catch (ConnectionException $exception) {
            throw RequestException::fromConnectionException($exception);
        }

        if ($response->failed()) {
            throw RequestException::fromResponse($response);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }

    /**
     * Shared HTTP client for Pixelforge.
     *
     * Retries only transient failures (connection errors, 429, 5xx).
     * Permanent client errors (400/401/404/409/422) are not retried.
     */
    protected function request(bool $retry = false): PendingRequest
    {
        $pending = Http::baseUrl((string) config('pixelforge.base_url'))
            ->withToken((string) config('pixelforge.api_key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((float) config('pixelforge.http.connect_timeout'))
            ->timeout((float) config('pixelforge.http.timeout'));

        if (! $retry) {
            return $pending;
        }

        $times = max(1, (int) config('pixelforge.http.retry.times'));
        $defaultSleepMs = max(0, (int) config('pixelforge.http.retry.sleep_milliseconds'));

        return $pending->retry(
            times: $times,
            sleepMilliseconds: function (int $attempt, mixed $exception) use ($defaultSleepMs): int {
                if (
                    $exception instanceof HttpClientRequestException
                    && $exception->response !== null
                    && $exception->response->status() === 429
                ) {
                    $retryAfter = $exception->response->header('Retry-After');

                    if (is_numeric($retryAfter)) {
                        return max($defaultSleepMs, (int) $retryAfter * 1000);
                    }
                }

                return $defaultSleepMs * max(1, $attempt);
            },
            when: function (Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if (! $exception instanceof HttpClientRequestException || $exception->response === null) {
                    return false;
                }

                $status = $exception->response->status();

                return $status === 429 || $status >= 500;
            },
            throw: false,
        );
    }
}
