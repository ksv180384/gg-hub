<?php

use App\Models\User;
use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventHistoryTitle;
use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function seedEventHistoryTitleGuild(): Guild
{
    $user = User::factory()->create();
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

    return Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Guild A',
        'slug' => 'guild-a',
        'owner_id' => $user->id,
        'is_recruiting' => false,
        'dkp_enabled' => true,
    ]);
}

it('lists event history titles with dkp and histories count', function () {
    $user = User::factory()->create();
    $title = EventHistoryTitle::query()->create([
        'name' => 'Raid',
        'dkp_base_points' => 15,
    ]);

    actingAs($user)
        ->getJson('/api/v1/event-history-titles?limit=0')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $title->id)
        ->assertJsonPath('data.0.name', 'Raid')
        ->assertJsonPath('data.0.dkp_base_points', 15)
        ->assertJsonPath('data.0.histories_count', 0);
});

it('creates event history title with dkp', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->postJson('/api/v1/event-history-titles', [
            'name' => 'Siege',
            'dkp_base_points' => 20,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Siege')
        ->assertJsonPath('data.dkp_base_points', 20);

    expect(EventHistoryTitle::query()->where('name', 'Siege')->value('dkp_base_points'))->toBe(20);
});

it('updates event history title name and dkp', function () {
    $user = User::factory()->create();
    $title = EventHistoryTitle::query()->create([
        'name' => 'Old name',
        'dkp_base_points' => 5,
    ]);

    actingAs($user)
        ->putJson("/api/v1/event-history-titles/{$title->id}", [
            'name' => 'New name',
            'dkp_base_points' => 12,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonPath('data.dkp_base_points', 12);
});

it('cannot delete event history title used in event history', function () {
    $user = User::factory()->create();
    $title = EventHistoryTitle::query()->create(['name' => 'Used title']);
    $guild = seedEventHistoryTitleGuild();

    EventHistory::query()->create([
        'guild_id' => $guild->id,
        'event_history_title_id' => $title->id,
        'occurred_at' => now(),
    ]);

    actingAs($user)
        ->deleteJson("/api/v1/event-history-titles/{$title->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);

    expect(EventHistoryTitle::query()->whereKey($title->id)->exists())->toBeTrue();
});

it('deletes unused event history title', function () {
    $user = User::factory()->create();
    $title = EventHistoryTitle::query()->create(['name' => 'Unused title']);

    actingAs($user)
        ->deleteJson("/api/v1/event-history-titles/{$title->id}")
        ->assertNoContent();

    expect(EventHistoryTitle::query()->whereKey($title->id)->exists())->toBeFalse();
});
