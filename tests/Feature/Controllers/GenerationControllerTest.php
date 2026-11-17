<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'pixelforge.base_url' => 'https://pixelforge.test/v1',
        'pixelforge.api_key' => 'test-key',
        'pixelforge.http.connect_timeout' => 1,
        'pixelforge.http.timeout' => 1,
        'pixelforge.http.retry.times' => 1,
        'pixelforge.http.retry.sleep_milliseconds' => 0,
    ]);
});

/**
 * @return array<string, mixed>
 */
function generationControllerPricingFixture(): array
{
    return json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/pixelforge-pricing.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function fakeGenerationControllerProvider(): void
{
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response(generationControllerPricingFixture()),
        'pixelforge.test/v1/generations' => Http::response([
            'id' => 'gen_01CONTROLLERSTORE00000000001',
            'object' => 'generation',
            'status' => 'queued',
            'prompt' => 'A quiet harbor at dusk',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'metadata' => [],
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'usage' => null,
            'error' => null,
        ], 201),
    ]);
}

it('redirects guests away from generation pages', function () {
    $this->get(route('generations.index'))->assertRedirect(route('login'));
    $this->get(route('generations.create'))->assertRedirect(route('login'));
    $this->post(route('generations.store'), [])->assertRedirect(route('login'));
});

it('renders the generate form for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('generations.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('generations/Create')
            ->has('sizes')
            ->has('qualities'));
});

it('submits a generation and redirects to the detail page', function () {
    fakeGenerationControllerProvider();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('generations.store'), [
        'prompt' => 'A quiet harbor at dusk',
        'size' => GenerationSize::Square->value,
        'quality' => GenerationQuality::Medium->value,
    ]);

    $generation = Generation::query()->where('user_id', $user->id)->first();

    expect($generation)->not->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBe('gen_01CONTROLLERSTORE00000000001');

    $response->assertRedirect(route('generations.show', $generation))
        ->assertSessionHas('status', 'Generation submitted.');
});

it('rejects invalid store payloads with validation messages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('generations.create'))
        ->post(route('generations.store'), [])
        ->assertRedirect(route('generations.create'))
        ->assertSessionHasErrors(['prompt', 'size', 'quality']);
});

it('lists only the authenticated user generations', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $own = Generation::factory()->create([
        'user_id' => $user->id,
        'prompt' => 'My generation',
    ]);

    Generation::factory()->create([
        'user_id' => $other->id,
        'prompt' => 'Someone else',
    ]);

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('generations/Index')
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $own->id)
            ->where('generations.data.0.prompt', 'My generation'));
});

it('shows an owned generation', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->succeeded()->create([
        'user_id' => $user->id,
        'prompt' => 'Owned detail',
    ]);

    $this->actingAs($user)
        ->get(route('generations.show', $generation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('generations/Show')
            ->where('generation.id', $generation->id)
            ->where('generation.prompt', 'Owned detail')
            ->where('generation.status', 'succeeded'));
});

it('returns 404 when viewing another users generation', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->create([
        'user_id' => User::factory(),
    ]);

    $this->actingAs($user)
        ->get(route('generations.show', $generation))
        ->assertNotFound();
});
