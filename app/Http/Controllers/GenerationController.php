<?php

namespace App\Http\Controllers;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use App\Services\GenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Generation::class);

        $generations = Generation::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(15)
            ->through(fn (Generation $generation): array => $this->generationPayload($generation));

        return Inertia::render('generations/Index', [
            'generations' => $generations,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Generation::class);

        return Inertia::render('generations/Create', [
            'sizes' => array_column(GenerationSize::cases(), 'value'),
            'qualities' => array_column(GenerationQuality::cases(), 'value'),
        ]);
    }

    public function store(StoreGenerationRequest $request, GenerationService $generations): RedirectResponse
    {
        $generation = $generations->submit(
            user: $request->user(),
            prompt: $request->prompt(),
            size: $request->size(),
            quality: $request->quality(),
            referenceImages: $request->referenceImages(),
        );

        return redirect()
            ->route('generations.show', $generation)
            ->with('status', 'Generation submitted.');
    }

    public function show(Generation $generation): Response
    {
        $this->authorize('view', $generation);

        return Inertia::render('generations/Show', [
            'generation' => $this->generationPayload($generation),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generationPayload(Generation $generation): array
    {
        return [
            'id' => $generation->id,
            'status' => $generation->status->value,
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'reference_images' => $generation->reference_images ?? [],
            'estimated_provider_cost' => $generation->estimated_provider_cost,
            'estimated_customer_cost' => $generation->estimated_customer_cost,
            'actual_provider_cost' => $generation->actual_provider_cost,
            'actual_customer_cost' => $generation->actual_customer_cost,
            'image_url' => $generation->image_url,
            'image_url_expires_at' => $generation->image_url_expires_at?->toIso8601String(),
            'error_code' => $generation->error_code,
            'error_message' => $generation->error_message,
            'submitted_at' => $generation->submitted_at?->toIso8601String(),
            'started_at' => $generation->started_at?->toIso8601String(),
            'completed_at' => $generation->completed_at?->toIso8601String(),
            'created_at' => $generation->created_at?->toIso8601String(),
        ];
    }
}
