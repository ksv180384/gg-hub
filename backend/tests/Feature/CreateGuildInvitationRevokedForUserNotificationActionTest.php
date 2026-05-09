<?php

use App\Actions\Notification\CreateGuildInvitationRevokedForUserNotificationAction;
use App\Models\Notification;
use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Enums\GuildApplicationStatus;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('creates notification for invited user when invitation is revoked', function () {
    $user = User::factory()->create();

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'is_active' => true,
    ]);

    $loc = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'ru',
        'name' => 'RU',
        'is_active' => true,
    ]);

    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'name' => 'Test Server',
        'slug' => 'test-server',
        'is_active' => true,
    ]);

    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Test Guild',
        'slug' => 'test-guild',
        'owner_id' => $user->id,
        'is_recruiting' => false,
    ]);

    $character = Character::query()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Char',
    ]);

    $application = GuildApplication::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $character->id,
        'form_data' => [],
        'status' => GuildApplicationStatus::Revoked->value,
    ]);

    $action = app(CreateGuildInvitationRevokedForUserNotificationAction::class);
    $notification = $action($application);

    expect($notification)->not->toBeNull();
    expect($notification)->toBeInstanceOf(Notification::class);
    expect($notification->user_id)->toBe($user->id);
    expect($notification->link)->toBe('/guilds/' . $guild->id . '/applications/my/' . $application->id);
});

