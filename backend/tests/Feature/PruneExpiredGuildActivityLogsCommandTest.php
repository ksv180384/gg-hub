<?php

use App\GuildActivityLog;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('deletes guild activity logs that have been stored for three months', function () {
    Carbon::setTestNow('2026-08-03 12:00:00');

    $user = User::factory()->create();
    $game = Game::query()->create([
        'name' => 'Retention Test Game',
        'slug' => 'retention-test-game',
        'is_active' => true,
    ]);
    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'ret',
        'name' => 'Retention Test Localization',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Retention Test Server',
        'slug' => 'retention-test-server',
        'is_active' => true,
    ]);
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Retention Test Guild',
        'slug' => 'retention-test-guild',
        'owner_id' => $user->id,
        'is_recruiting' => false,
    ]);

    $expiredAtCutoff = GuildActivityLog::factory()->forGuild($guild)->create([
        'created_at' => now()->subMonthsNoOverflow(3),
    ]);
    $expiredBeforeCutoff = GuildActivityLog::factory()->forGuild($guild)->create([
        'created_at' => now()->subMonthsNoOverflow(3)->subSecond(),
    ]);
    $active = GuildActivityLog::factory()->forGuild($guild)->create([
        'created_at' => now()->subMonthsNoOverflow(3)->addSecond(),
    ]);

    Artisan::call('guild-activity:prune-expired');

    expect(GuildActivityLog::query()->find($expiredAtCutoff->id))->toBeNull()
        ->and(GuildActivityLog::query()->find($expiredBeforeCutoff->id))->toBeNull()
        ->and(GuildActivityLog::query()->find($active->id))->not->toBeNull()
        ->and(Artisan::output())->toContain('Deleted 2 expired guild activity log(s).');
});
