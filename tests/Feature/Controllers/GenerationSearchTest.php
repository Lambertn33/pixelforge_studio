<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;

beforeEach(function () {
    config([
        'scout.driver' => 'collection',
        'scout.queue' => false,
    ]);

    app(EngineManager::class)->forgetDrivers();
});

it('lists postgres history when the search query is empty', function () {
    $user = User::factory()->create();

    $own = Generation::factory()->create([
        'user_id' => $user->id,
        'prompt' => 'a quiet harbor at dusk',
    ]);

    Generation::factory()->create([
        'user_id' => User::factory(),
        'prompt' => 'someone else harbor',
    ]);

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('generations/Index')
            ->where('filters.q', '')
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $own->id)
            ->where('generations.data.0.prompt', 'a quiet harbor at dusk'));
});

it('treats whitespace-only search as normal history', function () {
    $user = User::factory()->create();

    $own = Generation::factory()->create([
        'user_id' => $user->id,
        'prompt' => 'history without search',
    ]);

    $this->actingAs($user)
        ->get(route('generations.index', ['q' => '   ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.q', '')
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $own->id));
});

it('finds an exact prompt match for the authenticated user', function () {
    $user = User::factory()->create();

    $match = Generation::factory()->create([
        'user_id' => $user->id,
        'prompt' => 'a red bicycle on a hill',
    ]);

    Generation::factory()->create([
        'user_id' => $user->id,
        'prompt' => 'a blue sailboat at sea',
    ]);

    $this->actingAs($user)
        ->get(route('generations.index', ['q' => 'bicycle']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.q', 'bicycle')
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $match->id));
});

it('finds a partial prompt match', function () {
    $user = User::factory()->create();

    $match = Generation::factory()->create([
        'user_id' => $user->id,
        'prompt' => 'a mountain cabin in winter',
    ]);

    $this->actingAs($user)
        ->get(route('generations.index', ['q' => 'cabin']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $match->id));
});

it('does not return another users generation in search results', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Generation::factory()->create([
        'user_id' => $other->id,
        'prompt' => 'shared keyword lighthouse',
    ]);

    $own = Generation::factory()->create([
        'user_id' => $user->id,
        'prompt' => 'my own lighthouse sketch',
    ]);

    $this->actingAs($user)
        ->get(route('generations.index', ['q' => 'lighthouse']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $own->id)
            ->where('generations.data.0.prompt', 'my own lighthouse sketch'));
});

it('rejects an excessively long search query', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('generations.index', ['q' => str_repeat('a', 256)]))
        ->assertSessionHasErrors('q');
});

it('exposes the searchable payload shape expected by Typesense', function () {
    $generation = Generation::factory()->create([
        'prompt' => 'shape check prompt',
    ]);

    expect($generation->toSearchableArray())->toMatchArray([
        'id' => (string) $generation->id,
        'user_id' => $generation->user_id,
        'prompt' => 'shape check prompt',
        'status' => $generation->status->value,
        'size' => $generation->size->value,
        'created_at' => $generation->created_at->getTimestamp(),
    ]);
});

describe('Typesense', function () {
    beforeEach(function () {
        if (! typesenseIsReachable()) {
            $this->markTestSkipped('Typesense is not reachable');
        }

        config([
            'scout.driver' => 'typesense',
            'scout.queue' => false,
        ]);

        app(EngineManager::class)->forgetDrivers();
    });

    afterEach(function () {
        if (config('scout.driver') !== 'typesense') {
            return;
        }

        try {
            Artisan::call('scout:flush', ['model' => Generation::class]);
        } catch (Throwable) {
            // Keep suite resilient if the collection was never created.
        }

        app(EngineManager::class)->forgetDrivers();
    });

    it('matches a transposed typo like huose to house', function () {
        $user = User::factory()->create();

        $match = Generation::factory()->create([
            'user_id' => $user->id,
            'prompt' => 'a house by the lake',
        ]);

        $this->actingAs($user)
            ->get(route('generations.index', ['q' => 'huose']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'huose')
                ->has('generations.data', 1)
                ->where('generations.data.0.id', $match->id));
    });

    it('matches dgo to a prompt containing dog', function () {
        $user = User::factory()->create();

        $match = Generation::factory()->create([
            'user_id' => $user->id,
            'prompt' => 'a cute dog in the park',
        ]);

        $this->actingAs($user)
            ->get(route('generations.index', ['q' => 'dgo']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('generations.data', 1)
                ->where('generations.data.0.id', $match->id));
    });

    it('keeps search results scoped to the authenticated user', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Generation::factory()->create([
            'user_id' => $other->id,
            'prompt' => 'secret garden pavilion',
        ]);

        $own = Generation::factory()->create([
            'user_id' => $user->id,
            'prompt' => 'my garden pavilion',
        ]);

        $this->actingAs($user)
            ->get(route('generations.index', ['q' => 'garden']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('generations.data', 1)
                ->where('generations.data.0.id', $own->id));
    });

    it('indexes on create, updates on prompt change, and removes on delete', function () {
        $user = User::factory()->create();
        $token = (string) Str::uuid();
        $original = "index-sync-{$token}-original";
        $updated = "index-sync-{$token}-updated";

        $generation = Generation::factory()->create([
            'user_id' => $user->id,
            'prompt' => $original,
        ]);

        expect(
            Generation::search($original)->where('user_id', $user->id)->keys()->all()
        )->toContain((string) $generation->id);

        $generation->update(['prompt' => $updated]);

        expect(
            Generation::search($original)->where('user_id', $user->id)->keys()->all()
        )->not->toContain((string) $generation->id);

        expect(
            Generation::search($updated)->where('user_id', $user->id)->keys()->all()
        )->toContain((string) $generation->id);

        $generation->delete();

        expect(
            Generation::search($updated)->where('user_id', $user->id)->keys()->all()
        )->not->toContain((string) $generation->id);
    });
});

function typesenseIsReachable(): bool
{
    $node = config('scout.typesense.client-settings.nodes.0');
    $apiKey = (string) config('scout.typesense.client-settings.api_key');

    if (! is_array($node) || $apiKey === '') {
        return false;
    }

    $protocol = $node['protocol'] ?? 'http';
    $host = $node['host'] ?? 'localhost';
    $port = $node['port'] ?? '8108';
    $path = $node['path'] ?? '';

    try {
        $response = Http::timeout(1)
            ->withHeaders(['X-TYPESENSE-API-KEY' => $apiKey])
            ->get("{$protocol}://{$host}:{$port}{$path}/health");

        return $response->successful() && $response->json('ok') === true;
    } catch (Throwable) {
        return false;
    }
}
