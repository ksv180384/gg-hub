<?php

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Event\Models\Event;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('returns today calendar events across all user guilds with guild and game', function () {
    $user = User::factory()->create([
        'timezone' => 'UTC',
    ]);

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'is_active' => true,
    ]);

    $loc = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
    ]);

    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'name' => 'Test Server',
        'slug' => 'test-server',
        'is_active' => true,
    ]);

    $guildA = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Guild A',
        'slug' => 'guild-a',
        'owner_id' => $user->id,
        'is_recruiting' => false,
    ]);
    $guildB = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Guild B',
        'slug' => 'guild-b',
        'owner_id' => $user->id,
        'is_recruiting' => false,
    ]);

    $charA = Character::query()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Char A',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    $charB = Character::query()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Char B',
        'use_profile_avatar' => false,
        'is_main' => false,
    ]);

    GuildMember::query()->create([
        'guild_id' => $guildA->id,
        'character_id' => $charA->id,
        'joined_at' => now(),
    ]);
    GuildMember::query()->create([
        'guild_id' => $guildB->id,
        'character_id' => $charB->id,
        'joined_at' => now(),
    ]);

    $start = now()->startOfDay()->addHours(12);
    $end = now()->startOfDay()->addHours(13);

    Event::query()->create([
        'guild_id' => $guildA->id,
        'created_by_character_id' => $charA->id,
        'title' => 'Event A',
        'description' => null,
        'starts_at' => $start,
        'ends_at' => $end,
        'recurrence' => null,
        'recurrence_ends_at' => null,
    ]);
    Event::query()->create([
        'guild_id' => $guildB->id,
        'created_by_character_id' => $charB->id,
        'title' => 'Event B',
        'description' => null,
        'starts_at' => $start,
        'ends_at' => $end,
        'recurrence' => null,
        'recurrence_ends_at' => null,
    ]);

    actingAs($user)
        ->getJson('/api/v1/user/guild-calendar-events')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                ['id', 'title', 'starts_at', 'guild', 'game'],
            ],
        ])
        ->assertJsonFragment(['name' => 'Guild A'])
        ->assertJsonFragment(['name' => 'Guild B'])
        ->assertJsonFragment(['name' => 'Test Game']);
});

