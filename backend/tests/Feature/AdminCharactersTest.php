<?php

use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCharacterContext(string $gameName, string $gameSlug, string $serverName): array
{
    $game = Game::query()->create([
        'name' => $gameName,
        'slug' => $gameSlug,
        'is_active' => true,
    ]);
    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'ru',
        'name' => 'Russia',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => $serverName,
        'slug' => $gameSlug.'-server',
        'is_active' => true,
    ]);

    return [$game, $localization, $server];
}

it('filters and sorts the admin character list by every table column', function () {
    [$albion, $albionLocalization, $alphaServer] = createCharacterContext('Albion Online', 'albion', 'Alpha');
    [$warcraft, $warcraftLocalization, $omegaServer] = createCharacterContext('World of Warcraft', 'warcraft', 'Omega');

    $amy = User::factory()->create(['email' => 'amy@example.test']);
    $zoe = User::factory()->create(['email' => 'zoe@example.test']);

    Character::query()->create([
        'user_id' => $amy->id,
        'game_id' => $albion->id,
        'localization_id' => $albionLocalization->id,
        'server_id' => $alphaServer->id,
        'name' => 'Ranger',
    ]);
    Character::query()->create([
        'user_id' => $zoe->id,
        'game_id' => $warcraft->id,
        'localization_id' => $warcraftLocalization->id,
        'server_id' => $omegaServer->id,
        'name' => 'Mage',
    ]);

    $this->withoutMiddleware()
        ->getJson("/api/v1/admin/characters?name=Rang&email=amy&game_id={$albion->id}&server_id={$alphaServer->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ranger')
        ->assertJsonPath('data.0.user.email', 'amy@example.test')
        ->assertJsonPath('data.0.game.name', 'Albion Online')
        ->assertJsonPath('data.0.server.name', 'Alpha');

    foreach ([
        'name' => 'Ranger',
        'email' => 'Mage',
        'game' => 'Mage',
        'server' => 'Mage',
    ] as $sort => $expectedFirstCharacter) {
        $this->withoutMiddleware()
            ->getJson("/api/v1/admin/characters?sort={$sort}&direction=desc")
            ->assertOk()
            ->assertJsonPath('data.0.name', $expectedFirstCharacter);
    }
});

it('returns exactly 50 characters per page', function () {
    [$game, $localization, $server] = createCharacterContext('Lineage II', 'lineage', 'Teon');
    $user = User::factory()->create();

    foreach (range(1, 51) as $number) {
        Character::query()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'localization_id' => $localization->id,
            'server_id' => $server->id,
            'name' => sprintf('Character %02d', $number),
        ]);
    }

    $this->withoutMiddleware()
        ->getJson('/api/v1/admin/characters')
        ->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 51)
        ->assertJsonPath('meta.last_page', 2);
});
