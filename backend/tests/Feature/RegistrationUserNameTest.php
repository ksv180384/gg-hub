<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'newuser@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ], $overrides);
}

it('registers with explicit unique name', function () {
    $this->postJson('/api/v1/register', registrationPayload([
        'name' => 'MyNickname',
    ]))->assertCreated();

    expect(User::where('email', 'newuser@example.com')->value('name'))->toBe('MyNickname');
});

it('rejects registration when provided name is already taken', function () {
    User::factory()->create(['name' => 'TakenName']);

    $this->postJson('/api/v1/register', registrationPayload([
        'name' => 'TakenName',
        'email' => 'another@example.com',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('derives name from email when name is omitted', function () {
    $this->postJson('/api/v1/register', registrationPayload([
        'email' => 'cool.player@example.com',
    ]))->assertCreated();

    expect(User::where('email', 'cool.player@example.com')->value('name'))->toBe('cool.player');
});

it('derives name with numeric suffix when email base is taken', function () {
    User::factory()->create(['name' => 'cool.player']);

    $this->postJson('/api/v1/register', registrationPayload([
        'email' => 'cool.player@example.com',
    ]))->assertCreated();

    expect(User::where('email', 'cool.player@example.com')->value('name'))->toBe('cool.player2');
});
