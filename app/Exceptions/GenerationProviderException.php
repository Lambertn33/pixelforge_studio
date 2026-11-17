<?php

namespace App\Exceptions;

use App\Exceptions\Pixelforge\PixelforgeRequestException;
use App\Models\Generation;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GenerationProviderException extends Exception
{
    public function __construct(
        public readonly Generation $generation,
        string $message,
        public readonly PixelforgeRequestException $providerException,
    ) {
        parent::__construct($message, previous: $providerException);
    }

    public static function fromProvider(Generation $generation, PixelforgeRequestException $exception): self
    {
        return new self(
            generation: $generation,
            message: $generation->error_message ?? $exception->getMessage(),
            providerException: $exception,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'generation_id' => $this->generation->id,
            ...$this->providerException->context(),
        ];
    }

    public function render(Request $request): RedirectResponse|false
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return false;
        }

        return back()
            ->withInput()
            ->with('error', $this->getMessage());
    }
}
