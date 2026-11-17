<?php

namespace App\Services\Pixelforge;

use App\Enums\PixelforgeEventType;
use App\Exceptions\Pixelforge\InvalidWebhookPayloadException;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;

class WebhookReceiver
{
    /**
     * Persist a verified Pixelforge webhook. Duplicates return the existing row.
     *
     * @return array{event: WebhookEvent, was_created: bool}
     *
     * @throws InvalidWebhookPayloadException
     */
    public function receive(string $rawBody, ?string $eventIdHeader = null, ?string $eventTypeHeader = null): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            throw new InvalidWebhookPayloadException('Webhook payload must be valid JSON.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $decoded;

        $eventId = $this->resolveEventId($payload, $eventIdHeader);
        $eventType = $this->resolveEventType($payload, $eventTypeHeader);

        try {
            $event = WebhookEvent::query()->create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'generation_id' => null,
                'payload' => $payload,
                'received_at' => now(),
            ]);

            return [
                'event' => $event,
                'was_created' => true,
            ];
        } catch (UniqueConstraintViolationException) {
            /** @var WebhookEvent $event */
            $event = WebhookEvent::query()
                ->where('event_id', $eventId)
                ->firstOrFail();

            return [
                'event' => $event,
                'was_created' => false,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEventId(array $payload, ?string $eventIdHeader): string
    {
        $eventId = isset($payload['id']) && is_string($payload['id']) && $payload['id'] !== ''
            ? $payload['id']
            : $eventIdHeader;

        if ($eventId === null || $eventId === '') {
            throw new InvalidWebhookPayloadException('Webhook event id is missing.');
        }

        return $eventId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEventType(array $payload, ?string $eventTypeHeader): PixelforgeEventType
    {
        $type = isset($payload['type']) && is_string($payload['type']) && $payload['type'] !== ''
            ? $payload['type']
            : $eventTypeHeader;

        if ($type === null || $type === '') {
            throw new InvalidWebhookPayloadException('Webhook event type is missing.');
        }

        $eventType = PixelforgeEventType::tryFrom($type);

        if ($eventType === null) {
            throw new InvalidWebhookPayloadException("Unsupported webhook event type [{$type}].");
        }

        return $eventType;
    }
}
