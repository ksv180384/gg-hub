<?php

use App\Models\Game;
use App\Models\Localization;
use App\Models\Notification;
use App\Models\Server;
use App\Models\User;
use Domains\Character\Actions\CreateCharacterAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a notification after creating first character with guilds link filters', function () {
    $user = User::factory()->create();

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'description' => null,
        'image' => null,
        'is_active' => true,
        'max_classes_per_character' => 1,
        'party_size' => 5,
    ]);

    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'ru',
        'name' => 'RU',
        'is_active' => true,
    ]);

    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Server 1',
        'slug' => 'server-1',
        'is_active' => true,
        'merged_into_server_id' => null,
    ]);

    /** @var CreateCharacterAction $createCharacterAction */
    $createCharacterAction = app(CreateCharacterAction::class);

    $character = $createCharacterAction($user, [
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Hero',
    ]);

    expect($character->user_id)->toBe($user->id);
    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(1);

    $notification = Notification::query()->where('user_id', $user->id)->firstOrFail();
    expect($notification->link)->toBe(
        '/guilds?game_id='.$game->id
        .'&localization_ids='.$localization->id
        .'&server_ids='.$server->id
        .'&is_recruiting=1'
    );
});

it('does not create a notification after creating second character', function () {
    $user = User::factory()->create();

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'description' => null,
        'image' => null,
        'is_active' => true,
        'max_classes_per_character' => 1,
        'party_size' => 5,
    ]);

    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'ru',
        'name' => 'RU',
        'is_active' => true,
    ]);

    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Server 1',
        'slug' => 'server-1',
        'is_active' => true,
        'merged_into_server_id' => null,
    ]);

    /** @var CreateCharacterAction $createCharacterAction */
    $createCharacterAction = app(CreateCharacterAction::class);

    $createCharacterAction($user, [
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Hero',
    ]);

    $createCharacterAction($user, [
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Hero2',
    ]);

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(1);
});

