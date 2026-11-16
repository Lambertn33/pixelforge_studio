<?php

namespace Database\Factories;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'client_request_id' => (string) Str::uuid(),
            'pixelforge_id' => null,
            'prompt' => fake()->sentence(12),
            'size' => GenerationSize::Square,
            'quality' => GenerationQuality::Medium,
            'reference_images' => [],
            'metadata' => [],
            'status' => GenerationStatus::Queued,
            'estimated_provider_cost' => null,
            'estimated_customer_cost' => null,
            'actual_provider_cost' => null,
            'actual_customer_cost' => null,
            'pricing_snapshot' => null,
            'usage' => null,
            'image_url' => null,
            'image_url_expires_at' => null,
            'error_code' => null,
            'error_message' => null,
            'submitted_at' => now(),
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GenerationStatus::Processing,
            'pixelforge_id' => 'gen_'.Str::lower(Str::random(26)),
            'started_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GenerationStatus::Succeeded,
            'pixelforge_id' => 'gen_'.Str::lower(Str::random(26)),
            'started_at' => now()->subSeconds(20),
            'completed_at' => now(),
            'image_url' => 'https://pixelforge.promptedit.dev/images/example.png',
            'image_url_expires_at' => now()->addDay(),
            'actual_provider_cost' => '0.046125000',
            'actual_customer_cost' => '0.057656250',
            'usage' => [
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 500,
                'total_tokens' => 1612,
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GenerationStatus::Failed,
            'pixelforge_id' => 'gen_'.Str::lower(Str::random(26)),
            'started_at' => now()->subSeconds(10),
            'completed_at' => now(),
            'actual_provider_cost' => '0.000000000',
            'actual_customer_cost' => '0.000000000',
            'error_code' => 'generation_failed',
            'error_message' => 'The model could not produce an image for the prompt.',
        ]);
    }
}
