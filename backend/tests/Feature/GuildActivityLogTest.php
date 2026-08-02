<?php

use App\GuildActivityLog;
use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** @return array{user:User,guild:Guild,character:Character} */
function seedGuildActivityContext(string $suffix = 'a'): array
{
    $user = User::factory()->create(['name' => "Actor {$suffix}"]);
    $game = Game::query()->create([
        'name' => "Game {$suffix}",
        'slug' => "game-{$suffix}",
        'is_active' => true,
    ]);
    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => "l{$suffix}",
        'name' => "Localization {$suffix}",
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => "Server {$suffix}",
        'slug' => "server-{$suffix}",
        'is_active' => true,
    ]);
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => "Guild {$suffix}",
        'slug' => "guild-{$suffix}",
        'owner_id' => $user->id,
        'is_recruiting' => false,
    ]);
    $character = Character::query()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => "Character {$suffix}",
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    GuildMember::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $character->id,
        'joined_at' => now(),
    ]);
    $guild->update(['leader_character_id' => $character->id]);

    return compact('user', 'guild', 'character');
}

it('lists only current guild activity with fixed pagination of 50', function () {
    $context = seedGuildActivityContext('page');
    $other = seedGuildActivityContext('other');
    GuildActivityLog::query()->delete();

    GuildActivityLog::factory()
        ->count(55)
        ->forGuild($context['guild'])
        ->sequence(fn ($sequence): array => [
            'description' => "Entry {$sequence->index}",
            'created_at' => now()->subSeconds($sequence->index),
        ])
        ->create();

    GuildActivityLog::factory()
        ->forGuild($other['guild'])
        ->create(['description' => 'Other guild']);

    actingAs($context['user'])
        ->getJson("/api/v1/guilds/{$context['guild']->id}/activity")
        ->assertSuccessful()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 55)
        ->assertJsonPath('meta.last_page', 2);

    actingAs($context['user'])
        ->getJson("/api/v1/guilds/{$context['guild']->id}/activity?page=2")
        ->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

it('filters activity by inclusive date range category actor and search', function () {
    $context = seedGuildActivityContext('filters');
    GuildActivityLog::query()->delete();

    GuildActivityLog::factory()->forGuild($context['guild'])->create([
        'actor_user_id' => $context['user']->id,
        'actor_name' => $context['user']->name,
        'category' => GuildActivityLog::CATEGORY_AUCTION,
        'action' => 'auction.bid_placed',
        'description' => 'Ставка на Меч рассвета',
        'subject_name' => 'Меч рассвета',
        'created_at' => now()->setDate(2026, 8, 2)->setTime(12, 0),
    ]);
    GuildActivityLog::factory()->forGuild($context['guild'])->create([
        'category' => GuildActivityLog::CATEGORY_STORAGE,
        'action' => 'storage.item_created',
        'description' => 'Другой предмет',
        'created_at' => now()->setDate(2026, 7, 1)->setTime(12, 0),
    ]);

    actingAs($context['user'])
        ->getJson(
            "/api/v1/guilds/{$context['guild']->id}/activity"
            .'?created_from=2026-08-02'
            .'&created_to=2026-08-02'
            .'&category=auction'
            .'&actor_name=Actor'
            .'&search='.urlencode('Меч'),
        )
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'auction.bid_placed');
});

it('records storage mutations with the authenticated actor', function () {
    $context = seedGuildActivityContext('observer');
    GuildActivityLog::query()->delete();

    actingAs($context['user']);

    GuildBankItem::query()->create([
        'guild_id' => $context['guild']->id,
        'name' => 'Observer sword',
        'quantity' => 3,
    ]);

    $log = GuildActivityLog::query()->sole();

    expect($log->category)->toBe(GuildActivityLog::CATEGORY_STORAGE)
        ->and($log->action)->toBe('storage.item.created')
        ->and($log->actor_user_id)->toBe($context['user']->id)
        ->and($log->subject_name)->toBe('Observer sword');
});

it('authenticates and audits roulette actions through the internal channel', function () {
    $context = seedGuildActivityContext('roulette');
    GuildActivityLog::query()->delete();
    config()->set('services.socket_server.internal_token', 'test-internal-token');

    $token = actingAs($context['user'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/roulette/socket-token")
        ->assertSuccessful()
        ->json('data.token');

    $authentication = $this->postJson('/api/v1/guild-roulette/socket-auth', [
        'token' => $token,
    ])->assertSuccessful();

    $authentication
        ->assertJsonPath('data.guild_id', $context['guild']->id)
        ->assertJsonPath('data.user_id', $context['user']->id)
        ->assertJsonPath('data.character_ids.0', $context['character']->id);

    $this->withHeader('X-Socket-Internal-Token', 'test-internal-token')
        ->postJson('/api/v1/guild-roulette/audit', [
            'guild_id' => $context['guild']->id,
            'user_id' => $context['user']->id,
            'action' => 'roulette.spin_started',
            'metadata' => ['entries_count' => 4],
        ])
        ->assertSuccessful();

    $log = GuildActivityLog::query()->sole();

    expect($log->category)->toBe(GuildActivityLog::CATEGORY_ROULETTE)
        ->and($log->action)->toBe('roulette.spin_started')
        ->and($log->metadata)->toBe(['entries_count' => 4]);
});

it('forbids non-members from reading a guild history', function () {
    $context = seedGuildActivityContext('member');
    $outsider = User::factory()->create();

    actingAs($outsider)
        ->getJson("/api/v1/guilds/{$context['guild']->id}/activity")
        ->assertNotFound();
});
