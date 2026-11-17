<?php

namespace App\Http\Requests;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'string', Rule::enum(GenerationSize::class)],
            'quality' => ['required', 'string', Rule::enum(GenerationQuality::class)],
            'reference_images' => ['sometimes', 'nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'A prompt is required.',
            'prompt.max' => 'The prompt may not be greater than 4000 characters.',
            'size.Illuminate\Validation\Rules\Enum' => 'The size must be 1024x1024, 1536x1024, or 1024x1536.',
            'quality.Illuminate\Validation\Rules\Enum' => 'The quality must be low, medium, or high.',
            'reference_images.max' => 'You may attach up to 4 reference images.',
            'reference_images.*.url' => 'Each reference image must be an HTTP or HTTPS URL.',
        ];
    }

    public function prompt(): string
    {
        return $this->string('prompt')->toString();
    }

    public function size(): GenerationSize
    {
        return GenerationSize::from($this->string('size')->toString());
    }

    public function quality(): GenerationQuality
    {
        return GenerationQuality::from($this->string('quality')->toString());
    }

    /**
     * @return list<string>
     */
    public function referenceImages(): array
    {
        /** @var list<string> $images */
        $images = array_values($this->validated('reference_images') ?? []);

        return $images;
    }
}
