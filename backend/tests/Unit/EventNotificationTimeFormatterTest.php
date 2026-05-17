<?php

use App\Models\User;
use App\Services\Notifications\EventNotificationTimeFormatter;
use Domains\Character\Models\Character;
use Domains\Event\Models\Event;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('converts utc instant to creator timezone', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $game = Game::query()->create([
        'name' => 'G',
        'slug' => 'g',
        'is_active' => true,
    ]);
    $loc = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'en',
        'name' => 'EN',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'name' => 'S',
        'slug' => 's',
        'is_active' => true,
    ]);
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Guild',
        'slug' => 'guild',
        'owner_id' => $user->id,
        'is_recruiting' => false,
    ]);
    $character = Character::query()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Hero',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    $event = Event::query()->create([
        'guild_id' => $guild->id,
        'created_by_character_id' => $character->id,
        'title' => 'Raid',
        'starts_at' => '2026-05-16 10:10:00',
    ]);

    $formatter = new EventNotificationTimeFormatter;

    expect($formatter->timezoneFor($event))->toBe('Europe/Moscow')
        ->and($formatter->toLocal(Carbon::parse('2026-05-16 10:10:00', 'UTC'), 'Europe/Moscow')->format('H:i'))
        ->toBe('13:10');
});

it('falls back to utc when creator timezone is invalid', function () {
    $user = User::factory()->create(['timezone' => 'Not/A_Timezone']);
    $game = Game::query()->create([
        'name' => 'G2',
        'slug' => 'g2',
        'is_active' => true,
    ]);
    $loc = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'en',
        'name' => 'EN',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'name' => 'S',
        'slug' => 's2',
        'is_active' => true,
    ]);
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Guild',
        'slug' => 'guild2',
        'owner_id' => $user->id,
        'is_recruiting' => false,
    ]);
    $character = Character::query()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Hero',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    $event = Event::query()->create([
        'guild_id' => $guild->id,
        'created_by_character_id' => $character->id,
        'title' => 'Raid',
        'starts_at' => now(),
    ]);

    expect((new EventNotificationTimeFormatter)->timezoneFor($event))->toBe('UTC');
});
