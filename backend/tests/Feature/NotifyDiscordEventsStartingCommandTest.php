<?php

use App\Actions\Notification\SendGuildDiscordNotificationAction;
use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Event\Models\Event;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function createDiscordEventFixture(string $userTimezone, string $startsAtUtc): array
{
    $user = User::factory()->create(['timezone' => $userTimezone]);
    $game = Game::query()->create([
        'name' => 'G',
        'slug' => 'g-discord',
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
        'slug' => 's-discord',
        'is_active' => true,
    ]);
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Guild',
        'slug' => 'guild-discord',
        'owner_id' => $user->id,
        'is_recruiting' => false,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/1/token',
        'discord_notify_event_starting' => true,
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
        'title' => 'Рейд',
        'starts_at' => $startsAtUtc,
        'send_discord_notification' => true,
        'recurrence' => 'once',
    ]);

    return compact('guild', 'event');
}

it('sends discord notification with start time in creator timezone', function () {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00', 'UTC'));

    createDiscordEventFixture('Europe/Moscow', '2026-05-16 10:10:00');

    $capturedMessage = null;
    $action = Mockery::mock(SendGuildDiscordNotificationAction::class);
    $action->shouldReceive('__invoke')
        ->once()
        ->withArgs(function ($guild, $key, $message) use (&$capturedMessage) {
            $capturedMessage = $message;

            return $key === 'discord_notify_event_starting';
        });
    app()->instance(SendGuildDiscordNotificationAction::class, $action);

    Artisan::call('discord:notify-events-starting');

    expect($capturedMessage)
        ->toContain('старт в 13:10')
        ->toContain('Рейд');
});

it('sends discord notification with utc time when creator uses utc', function () {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00', 'UTC'));

    createDiscordEventFixture('UTC', '2026-05-16 10:10:00');

    $capturedMessage = null;
    $action = Mockery::mock(SendGuildDiscordNotificationAction::class);
    $action->shouldReceive('__invoke')
        ->once()
        ->withArgs(function ($guild, $key, $message) use (&$capturedMessage) {
            $capturedMessage = $message;

            return true;
        });
    app()->instance(SendGuildDiscordNotificationAction::class, $action);

    Artisan::call('discord:notify-events-starting');

    expect($capturedMessage)->toContain('старт в 10:10');
});

