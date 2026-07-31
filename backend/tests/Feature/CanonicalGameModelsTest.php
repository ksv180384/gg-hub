<?php

use Domains\Game\Models\Game;
use Domains\Game\Models\GameClass;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('uses the domain game models for all game relationships', function () {
    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'is_active' => true,
    ]);

    $localization = $game->localizations()->create([
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
    ]);

    $targetServer = $game->servers()->create([
        'localization_id' => $localization->id,
        'name' => 'Target Server',
        'slug' => 'target-server',
        'is_active' => true,
    ]);

    $sourceServer = $game->servers()->create([
        'localization_id' => $localization->id,
        'name' => 'Source Server',
        'slug' => 'source-server',
        'is_active' => false,
        'merged_into_server_id' => $targetServer->id,
    ]);

    $gameClass = $game->gameClasses()->create([
        'name' => 'Warrior',
        'slug' => 'warrior',
    ]);

    expect($game->localizations()->first())->toBeInstanceOf(Localization::class)
        ->and($game->servers()->first())->toBeInstanceOf(Server::class)
        ->and($sourceServer->mergedInto)->toBeInstanceOf(Server::class)
        ->and($gameClass)->toBeInstanceOf(GameClass::class)
        ->and($gameClass->game)->toBeInstanceOf(Game::class)
        ->and(class_exists('App\Models\Game'))->toBeFalse()
        ->and(class_exists('App\Models\Localization'))->toBeFalse()
        ->and(class_exists('App\Models\Server'))->toBeFalse();

    $this->getJson('/api/v1/games/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.id', $game->id);
});

it('keeps game image cleanup on the domain model', function () {
    Storage::fake('public');

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'image' => 'games/test-game/images/original.jpg',
        'is_active' => true,
    ]);

    Storage::disk('public')->put($game->image, 'image');

    $game->delete();

    Storage::disk('public')->assertMissing('games/test-game/images/original.jpg');
});
