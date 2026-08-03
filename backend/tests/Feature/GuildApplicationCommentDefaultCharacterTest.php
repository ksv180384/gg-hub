<?php

use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildMember;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('selects the application character by default for its owner', function (string $status) {
    $guildOwner = User::factory()->create();
    $applicationOwner = User::factory()->create();

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'is_active' => true,
    ]);
    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Test Server',
        'slug' => 'test-server',
        'is_active' => true,
    ]);
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Test Guild',
        'slug' => 'test-guild',
        'owner_id' => $guildOwner->id,
        'is_recruiting' => true,
    ]);

    $memberCharacter = Character::query()->create([
        'user_id' => $applicationOwner->id,
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Existing Member',
    ]);
    GuildMember::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $memberCharacter->id,
        'joined_at' => now()->subDay(),
    ]);

    $applicationCharacter = Character::query()->create([
        'user_id' => $applicationOwner->id,
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Application Character',
    ]);
    $application = GuildApplication::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $applicationCharacter->id,
        'form_data' => [],
        'status' => $status,
    ]);

    actingAs($applicationOwner)
        ->getJson("/api/v1/guilds/{$guild->id}/applications/{$application->id}/comments")
        ->assertOk()
        ->assertJsonPath('meta.default_character_id', $applicationCharacter->id)
        ->assertJsonPath('meta.my_characters.0.id', $applicationCharacter->id)
        ->assertJsonCount(2, 'meta.my_characters');
})->with(['pending', 'invitation']);
