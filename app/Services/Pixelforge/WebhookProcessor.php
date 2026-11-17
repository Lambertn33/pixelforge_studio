<?php

namespace App\Services\Pixelforge;

use App\Enums\PixelforgeEventType;
use App\Models\Generation;
use App\Models\WebhookEvent;
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

        $this->associateGeneration($event);

        // Status / cost application lands in 6.6–6.7.
    }

    private function associateGeneration(WebhookEvent $event): void
    {
        $object = $this->generationObject($event);

        if ($object === null) {
            return;
        }

        $generation = $event->generation_id !== null
            ? Generation::query()->find($event->generation_id)
            : $this->findGeneration($object);

        if ($generation === null) {
            return;
        }

        if ($event->generation_id === null) {
            $event->forceFill(['generation_id' => $generation->id])->save();
        }

        $this->backfillPixelforgeId($generation, $object);
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
