<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects login for unverified email registration with validation message on email field', function () {
    $user = User::factory()->unverified()->create([
        'password' => bcrypt('password'),
        'provider' => null,
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => false,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($response->json('errors.email.0'))
        ->toContain('подтвердить email');

    expect($response->json('message'))
        ->toContain('подтвердить email');
});
