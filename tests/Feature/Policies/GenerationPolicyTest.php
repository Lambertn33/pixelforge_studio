<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows authenticated users to list and create generations', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Generation::class))->toBeTrue()
        ->and($user->can('create', Generation::class))->toBeTrue();
});

it('allows owners to view and delete their generation', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->for($user)->create();

    expect($user->can('view', $generation))->toBeTrue()
        ->and($user->can('delete', $generation))->toBeTrue();
});

it('hides other users generations as not found', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $generation = Generation::factory()->for($owner)->create();

    $view = Gate::forUser($stranger)->inspect('view', $generation);
    $delete = Gate::forUser($stranger)->inspect('delete', $generation);

    expect($view->denied())->toBeTrue()
        ->and($view->status())->toBe(404)
        ->and($delete->denied())->toBeTrue()
        ->and($delete->status())->toBe(404)
        ->and($stranger->can('view', $generation))->toBeFalse()
        ->and($stranger->can('delete', $generation))->toBeFalse();
});

it('denies updating generations', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->for($user)->create();

    expect($user->can('update', $generation))->toBeFalse();
});
