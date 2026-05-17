<?php

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Actions\GetUserGuildsForGameAction;
use Domains\Guild\Enums\GuildApplicationStatus;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildMember;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('counts pending applications and active invitations for guild menu', function () {
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

    GuildMember::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $character->id,
        'joined_at' => now(),
    ]);

    GuildApplication::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $character->id,
        'form_data' => [],
        'status' => GuildApplicationStatus::Pending->value,
    ]);

    GuildApplication::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $character->id,
        'form_data' => [],
        'status' => GuildApplicationStatus::Invitation->value,
    ]);

    $permAction = mock(GetUserGuildPermissionSlugsAction::class);
    $permAction
        ->shouldReceive('__invoke')
        ->andReturn(collect(['podtverzdenie-ili-otklonenie-zaiavok']));

    $action = new GetUserGuildsForGameAction($permAction);
    $result = $action($user, $game->id)->values()->all();

    expect($result)->toHaveCount(1);
    expect($result[0]['can_invite'])->toBeTrue();
    expect($result[0]['pending_applications_count'])->toBe(2);
});

