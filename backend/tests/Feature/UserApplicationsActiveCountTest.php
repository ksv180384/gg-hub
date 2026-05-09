<?php

use App\Models\User;
use Domains\Guild\Actions\CountUserActiveGuildApplicationsAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('returns active applications count for authenticated user', function () {
    $user = User::factory()->create();

    mock(CountUserActiveGuildApplicationsAction::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andReturn(3);

    $this
        ->actingAs($user)
        ->getJson('/api/v1/user/applications/active-count')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                'count' => 3,
            ],
        ]);
});

