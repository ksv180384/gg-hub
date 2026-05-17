<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows user to keep their current name', function () {
    $user = User::factory()->create(['name' => 'MyName']);

    $this->actingAs($user)
        ->postJson('/api/v1/user', ['name' => 'MyName'])
        ->assertSuccessful();

    expect($user->fresh()->name)->toBe('MyName');
});

it('rejects profile update when name is taken by another user', function () {
    User::factory()->create(['name' => 'TakenName']);
    $user = User::factory()->create(['name' => 'OtherName']);

    $this->actingAs($user)
        ->postJson('/api/v1/user', ['name' => 'TakenName'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($user->fresh()->name)->toBe('OtherName');
});

it('updates profile when new name is unique', function () {
    $user = User::factory()->create(['name' => 'OldName']);

    $this->actingAs($user)
        ->postJson('/api/v1/user', ['name' => 'NewUniqueName'])
        ->assertSuccessful();

    expect($user->fresh()->name)->toBe('NewUniqueName');
});
