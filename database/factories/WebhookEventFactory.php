<?php

namespace Database\Factories;

use App\Enums\PixelforgeEventType;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventId = 'evt_'.Str::upper(Str::random(26));
        $pixelforgeId = 'gen_'.Str::upper(Str::random(26));

        return [
            'event_id' => $eventId,
            'event_type' => PixelforgeEventType::GenerationProcessing,
            'generation_id' => null,
            'payload' => [
                'id' => $eventId,
                'object' => 'event',
                'type' => PixelforgeEventType::GenerationProcessing->value,
                'created_at' => now()->toIso8601String(),
                'data' => [
                    'object' => [
                        'id' => $pixelforgeId,
                        'object' => 'generation',
                        'status' => 'processing',
                        'metadata' => [],
                    ],
                ],
            ],
            'received_at' => now(),
            'processed_at' => null,
            'processing_error' => null,
        ];
    }

    public function forGeneration(Generation $generation): static
    {
        return $this->state(fn (array $attributes) => [
            'generation_id' => $generation->id,
            'payload' => array_replace_recursive($attributes['payload'] ?? [], [
                'data' => [
                    'object' => [
                        'id' => $generation->pixelforge_id,
                        'metadata' => [
                            'client_request_id' => $generation->client_request_id,
                        ],
                    ],
                ],
            ]),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => PixelforgeEventType::GenerationSucceeded,
            'payload' => array_replace_recursive($attributes['payload'] ?? [], [
                'type' => PixelforgeEventType::GenerationSucceeded->value,
                'data' => [
                    'object' => [
                        'status' => 'succeeded',
                    ],
                ],
            ]),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => PixelforgeEventType::GenerationFailed,
            'payload' => array_replace_recursive($attributes['payload'] ?? [], [
                'type' => PixelforgeEventType::GenerationFailed->value,
                'data' => [
                    'object' => [
                        'status' => 'failed',
                    ],
                ],
            ]),
        ]);
    }

    public function ping(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => PixelforgeEventType::Ping,
            'payload' => [
                'id' => $attributes['event_id'] ?? 'evt_'.Str::upper(Str::random(26)),
                'object' => 'event',
                'type' => PixelforgeEventType::Ping->value,
                'created_at' => now()->toIso8601String(),
                'data' => [
                    'object' => null,
                ],
            ],
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => now(),
        ]);
    }
}
