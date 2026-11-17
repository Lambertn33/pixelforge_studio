<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Enums\PixelforgeEventType;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

            return;
        }

        $generation = $this->associateGeneration($event);

        if ($generation === null) {
            return;
        }

        $this->applyEvent($event, $generation);
        $event->markProcessed();
    }

    private function associateGeneration(WebhookEvent $event): ?Generation
    {
        $object = $this->generationObject($event);

        if ($object === null) {
            return null;
        }

        $generation = $event->generation_id !== null
            ? Generation::query()->find($event->generation_id)
            : $this->findGeneration($object);

        if ($generation === null) {
            return null;
        }

        if ($event->generation_id === null) {
            $event->forceFill(['generation_id' => $generation->id])->save();
        }

        $this->backfillPixelforgeId($generation, $object);

        return $generation->fresh();
    }

    private function applyEvent(WebhookEvent $event, Generation $generation): void
    {
        $object = $this->generationObject($event) ?? [];

        DB::transaction(function () use ($event, $generation, $object): void {
            /** @var Generation $locked */
            $locked = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isFinished()) {
                return;
            }

            $updates = match ($event->event_type) {
                PixelforgeEventType::GenerationProcessing => $this->processingUpdates($locked, $object),
                PixelforgeEventType::GenerationSucceeded => $this->succeededUpdates($locked, $object),
                PixelforgeEventType::GenerationFailed => $this->failedUpdates($locked, $object),
                PixelforgeEventType::Ping => [],
            };

            if ($updates !== []) {
                $locked->forceFill($updates)->save();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function processingUpdates(Generation $generation, array $object): array
    {
        return [
            'status' => GenerationStatus::Processing,
            'started_at' => $generation->started_at ?? $this->timestamp($object, 'started_at') ?? now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function succeededUpdates(Generation $generation, array $object): array
    {
        $updates = [
            'status' => GenerationStatus::Succeeded,
            'started_at' => $generation->started_at ?? $this->timestamp($object, 'started_at') ?? now(),
            'completed_at' => $this->timestamp($object, 'completed_at') ?? now(),
        ];

        if (isset($object['image_url']) && is_string($object['image_url']) && $object['image_url'] !== '') {
            $updates['image_url'] = $object['image_url'];
            $updates['image_url_expires_at'] = $this->imageUrlExpiresAt($object['image_url']);
        }

        if (isset($object['usage']) && is_array($object['usage'])) {
            $updates['usage'] = $object['usage'];
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function failedUpdates(Generation $generation, array $object): array
    {
        $error = isset($object['error']) && is_array($object['error']) ? $object['error'] : [];

        return [
            'status' => GenerationStatus::Failed,
            'started_at' => $generation->started_at ?? $this->timestamp($object, 'started_at') ?? now(),
            'completed_at' => $this->timestamp($object, 'completed_at') ?? now(),
            'error_code' => isset($error['code']) && is_string($error['code']) ? $error['code'] : 'generation_failed',
            'error_message' => isset($error['message']) && is_string($error['message'])
                ? $error['message']
                : 'The generation failed.',
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function timestamp(array $object, string $key): ?Carbon
    {
        $value = $object[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function imageUrlExpiresAt(string $imageUrl): ?Carbon
    {
        $expires = parse_url($imageUrl, PHP_URL_QUERY);

        if (! is_string($expires) || $expires === '') {
            return null;
        }

        parse_str($expires, $query);

        if (! isset($query['expires']) || ! is_numeric($query['expires'])) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $query['expires']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function generationObject(WebhookEvent $event): ?array
    {
        $object = $event->payload['data']['object'] ?? null;

        return is_array($object) ? $object : null;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $pixelforgeId = isset($object['id']) && is_string($object['id']) && $object['id'] !== ''
            ? $object['id']
            : null;

        if ($pixelforgeId !== null) {
            $byProviderId = Generation::query()
                ->where('pixelforge_id', $pixelforgeId)
                ->first();

            if ($byProviderId !== null) {
                return $byProviderId;
            }
        }

        $clientRequestId = data_get($object, 'metadata.client_request_id');

        if (! is_string($clientRequestId) || $clientRequestId === '') {
            return null;
        }

        return Generation::query()
            ->where('client_request_id', $clientRequestId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function backfillPixelforgeId(Generation $generation, array $object): void
    {
        $pixelforgeId = isset($object['id']) && is_string($object['id']) && $object['id'] !== ''
            ? $object['id']
            : null;

        if ($pixelforgeId === null) {
            return;
        }

        DB::transaction(function () use ($generation, $pixelforgeId): void {
            /** @var Generation|null $locked */
            $locked = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->pixelforge_id !== null) {
                return;
            }

            $locked->forceFill(['pixelforge_id' => $pixelforgeId])->save();
        });
    }
}
