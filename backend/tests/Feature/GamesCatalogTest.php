<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns compact catalog for games', function () {
    Game::query()->create([
        'name' => 'Throne and Liberty',
        'slug' => 'tl',
        'description' => 'Should not be returned',
        'image' => null,
        'is_active' => true,
    ]);

    Game::query()->create([
        'name' => 'Inactive',
        'slug' => 'inactive',
        'description' => 'Hidden',
        'image' => null,
        'is_active' => false,
    ]);

    $res = $this->getJson('/api/games/catalog');

    $res->assertOk();
    $res->assertJsonCount(1, 'data');
    $res->assertJsonPath('data.0.name', 'Throne and Liberty');
    $res->assertJsonMissing(['description' => 'Should not be returned']);
    $res->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'slug', 'image_preview', 'is_active'],
        ],
    ]);
});

