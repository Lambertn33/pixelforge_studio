<?php

use App\Http\Requests\StoreGenerationRequest;
use Illuminate\Support\Facades\Validator;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validGenerationPayload(array $overrides = []): array
{
    return array_merge([
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => '1024x1024',
        'quality' => 'high',
        'reference_images' => [
            'https://example.com/style.png',
        ],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $data
 */
function generationValidator(array $data): Illuminate\Validation\Validator
{
    return Validator::make($data, (new StoreGenerationRequest)->rules());
}

it('accepts a valid generation payload', function () {
    $validator = generationValidator(validGenerationPayload());

    expect($validator->passes())->toBeTrue();
});

it('accepts a payload without reference images', function () {
    $payload = validGenerationPayload();
    unset($payload['reference_images']);

    expect(generationValidator($payload)->passes())->toBeTrue();
});

it('rejects a missing prompt', function () {
    $validator = generationValidator(validGenerationPayload([
        'prompt' => '',
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('prompt'))->toBeTrue();
});

it('rejects a prompt longer than 4000 characters', function () {
    $validator = generationValidator(validGenerationPayload([
        'prompt' => str_repeat('a', 4001),
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('prompt'))->toBeTrue();
});

it('accepts a 4000 character prompt', function () {
    $validator = generationValidator(validGenerationPayload([
        'prompt' => str_repeat('ä', 4000),
    ]));

    expect($validator->passes())->toBeTrue();
});

it('rejects an invalid size', function () {
    $validator = generationValidator(validGenerationPayload([
        'size' => '512x512',
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('size'))->toBeTrue();
});

it('rejects an invalid quality', function () {
    $validator = generationValidator(validGenerationPayload([
        'quality' => 'ultra',
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('quality'))->toBeTrue();
});

it('rejects more than four reference images', function () {
    $validator = generationValidator(validGenerationPayload([
        'reference_images' => [
            'https://example.com/1.png',
            'https://example.com/2.png',
            'https://example.com/3.png',
            'https://example.com/4.png',
            'https://example.com/5.png',
        ],
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('reference_images'))->toBeTrue();
});

it('rejects non http reference image urls', function () {
    $validator = generationValidator(validGenerationPayload([
        'reference_images' => [
            'ftp://example.com/style.png',
        ],
    ]));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('reference_images.0'))->toBeTrue();
});
