<?php

use App\Models\User;
use Domains\User\Actions\ResolveRegistrationUserNameFromEmailAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses email local part as base name', function () {
    $name = app(ResolveRegistrationUserNameFromEmailAction::class)('John.Doe@Example.com');

    expect($name)->toBe('john.doe');
});

it('appends numeric suffix when base name is taken', function () {
    User::factory()->create(['name' => 'player']);

    $name = app(ResolveRegistrationUserNameFromEmailAction::class)('player@example.com');

    expect($name)->toBe('player2');
});

it('increments suffix until a free name is found', function () {
    User::factory()->create(['name' => 'player']);
    User::factory()->create(['name' => 'player2']);

    $name = app(ResolveRegistrationUserNameFromEmailAction::class)('player@example.com');

    expect($name)->toBe('player3');
});

it('falls back to user when email local part is empty', function () {
    $name = app(ResolveRegistrationUserNameFromEmailAction::class)('@example.com');

    expect($name)->toBe('user');
});
