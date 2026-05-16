<?php

use App\Models\User;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\GuildRole;
use Domains\Access\Models\Permission;
use Domains\Access\Models\PermissionGroup;
use Domains\Character\Models\Character;
use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventHistoryParticipant;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Domains\GuildDkp\Models\GuildUserDkpBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function seedGuildDkpPermissions(): void
{
    $bankGroup = PermissionGroup::query()->firstOrCreate(
        [
            'scope' => PermissionScope::Guild,
            'slug' => 'bank',
        ],
        [
            'name' => 'Хранилище гильдии',
        ]
    );

    foreach ([
        ['slug' => 'dobavliat-predmety', 'name' => 'Добавлять предметы'],
        ['slug' => 'peredavat-predmety-polzovateliam', 'name' => 'Передавать предметы пользователям'],
    ] as $permission) {
        Permission::query()->firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => $permission['slug'],
            ],
            [
                'name' => $permission['name'],
                'description' => $permission['name'],
                'permission_group_id' => $bankGroup->id,
            ]
        );
    }
}

function seedGuildDkpContext(): array
{
    seedGuildDkpPermissions();

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
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Guild A',
        'slug' => 'guild-a',
        'owner_id' => $user->id,
        'is_recruiting' => false,
        'dkp_enabled' => true,
    ]);
    $char = Character::query()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Char A',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    GuildMember::query()->create([
        'guild_id' => $guild->id,
        'character_id' => $char->id,
        'joined_at' => now(),
    ]);

    $guild->update(['leader_character_id' => $char->id]);

    return compact('user', 'guild', 'char');
}

it('charges and refunds dkp on bank grant lifecycle', function () {
    $ctx = seedGuildDkpContext();

    $item = GuildBankItem::query()->create([
        'guild_id' => $ctx['guild']->id,
        'name' => 'Sword',
        'description' => null,
        'guild_bank_item_tier_id' => null,
        'dkp_cost' => 10,
        'quantity' => 1,
    ]);

    GuildUserDkpBalance::query()->create([
        'guild_id' => $ctx['guild']->id,
        'user_id' => $ctx['user']->id,
        'balance' => 5,
    ]);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants", [
            'guild_bank_item_id' => $item->id,
            'received_by_character_id' => $ctx['char']->id,
            'reason' => 'Raid',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('data.requires_confirmation', true);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants", [
            'guild_bank_item_id' => $item->id,
            'received_by_character_id' => $ctx['char']->id,
            'reason' => 'Raid',
            'confirm_negative_balance' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.dkp_charged', 10);

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(-5);

    $grantId = GuildBankItemGrant::query()->value('id');

    actingAs($ctx['user'])
        ->deleteJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants/{$grantId}")
        ->assertSuccessful();

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(5);
});

it('adjusts member dkp manually and lists ledger entries', function () {
    $ctx = seedGuildDkpContext();

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/members/{$ctx['char']->id}/dkp/adjust", [
            'amount' => 15,
            'reason' => 'Bonus',
        ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 15)
        ->assertJsonPath('data.source', 'manual');

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/members/{$ctx['char']->id}/dkp")
        ->assertSuccessful()
        ->assertJsonPath('data.balance', 15);

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/dkp/ledger")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', 15)
        ->assertJsonMissingPath('data.0.guild_id');
});

it('filters ledger entries by period, user name and event title', function () {
    $ctx = seedGuildDkpContext();

    $otherUser = User::factory()->create(['name' => 'OtherUser']);
    $otherChar = Character::query()->create([
        'user_id' => $otherUser->id,
        'game_id' => $ctx['guild']->game_id,
        'localization_id' => $ctx['guild']->localization_id,
        'server_id' => $ctx['guild']->server_id,
        'name' => 'Other Char',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    GuildMember::query()->create([
        'guild_id' => $ctx['guild']->id,
        'character_id' => $otherChar->id,
        'joined_at' => now(),
    ]);

    $title = \Domains\Event\Models\EventHistoryTitle::query()->create(['name' => 'Raid night']);
    $otherTitle = \Domains\Event\Models\EventHistoryTitle::query()->create(['name' => 'PvP']);

    $history = EventHistory::query()->create([
        'guild_id' => $ctx['guild']->id,
        'event_history_title_id' => $title->id,
        'description' => null,
        'occurred_at' => now()->subDays(2),
        'dkp_base_points' => 10,
    ]);

    $participant = EventHistoryParticipant::query()->create([
        'event_history_id' => $history->id,
        'character_id' => $ctx['char']->id,
        'external_name' => null,
        'dkp_coefficient' => 1,
        'dkp_points_override' => null,
    ]);

    app(\Domains\GuildDkp\Actions\SyncEventHistoryDkpLedgerAction::class)($history->fresh(['guild', 'participants.character']));

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/members/{$otherChar->id}/dkp/adjust", [
            'amount' => 5,
            'reason' => 'Manual',
        ])
        ->assertCreated();

    $manualOccurredAt = now()->subDay()->startOfDay()->addHours(12);
    GuildDkpLedgerEntry::query()
        ->where('source', 'manual')
        ->update(['occurred_at' => $manualOccurredAt]);

    GuildDkpLedgerEntry::query()
        ->where('source', 'event')
        ->update(['occurred_at' => now()->subDays(2)->startOfDay()->addHours(18)]);

    $from = now()->subDays(3)->toDateString();
    $to = now()->subDay()->toDateString();

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/dkp/ledger?user_name={$ctx['user']->name}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source', 'event');

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/dkp/ledger?occurred_from={$from}&occurred_to={$to}")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/dkp/ledger?event_history_title_id={$title->id}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event_history.id', $history->id);

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/dkp/ledger?event_history_title_id={$otherTitle->id}")
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/dkp/ledger?source=manual")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source', 'manual');

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/dkp/ledger?source=event")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source', 'event');
});

it('syncs event history dkp into user balance and ledger', function () {
    $ctx = seedGuildDkpContext();

    $history = EventHistory::query()->create([
        'guild_id' => $ctx['guild']->id,
        'event_history_title_id' => null,
        'title' => 'Raid',
        'description' => null,
        'occurred_at' => now(),
        'dkp_base_points' => 10,
    ]);

    $participant = EventHistoryParticipant::query()->create([
        'event_history_id' => $history->id,
        'character_id' => $ctx['char']->id,
        'external_name' => null,
        'dkp_coefficient' => 1,
        'dkp_points_override' => null,
    ]);

    app(\Domains\GuildDkp\Actions\SyncEventHistoryDkpLedgerAction::class)($history->fresh(['guild', 'participants.character']));

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(10);

    expect(GuildDkpLedgerEntry::query()
        ->where('event_history_participant_id', $participant->id)
        ->value('amount'))->toBe(10);
});

it('updates guild member dkp coefficient', function () {
    $ctx = seedGuildDkpContext();

    actingAs($ctx['user'])
        ->putJson("/api/v1/guilds/{$ctx['guild']->id}/members/{$ctx['char']->id}/dkp-coefficient", [
            'dkp_coefficient' => 1.5,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.dkp_coefficient', 1.5);

    expect((float) GuildMember::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('character_id', $ctx['char']->id)
        ->value('dkp_coefficient'))->toBe(1.5);
});

it('exposes can manage dkp coefficient on roster member', function () {
    $ctx = seedGuildDkpContext();

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/roster/{$ctx['char']->id}")
        ->assertSuccessful()
        ->assertJsonPath('can_manage_dkp_coefficient', true)
        ->assertJsonPath('data.dkp_coefficient', 1);
});

it('forbids updating guild member dkp coefficient without permission', function () {
    $ctx = seedGuildDkpContext();

    $role = GuildRole::query()->create([
        'guild_id' => $ctx['guild']->id,
        'name' => 'Member',
        'slug' => 'member',
    ]);
    GuildMember::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('character_id', $ctx['char']->id)
        ->update(['guild_role_id' => $role->id]);
    $ctx['guild']->update(['leader_character_id' => null]);

    actingAs($ctx['user'])
        ->putJson("/api/v1/guilds/{$ctx['guild']->id}/members/{$ctx['char']->id}/dkp-coefficient", [
            'dkp_coefficient' => 2,
        ])
        ->assertForbidden();
});

it('uses guild member dkp coefficient when creating event history', function () {
    $ctx = seedGuildDkpContext();

    GuildMember::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('character_id', $ctx['char']->id)
        ->update(['dkp_coefficient' => 2.5]);

    $history = app(\Domains\Event\Actions\CreateEventHistoryAction::class)([
        'guild_id' => $ctx['guild']->id,
        'title' => 'Raid',
        'occurred_at' => now()->toIso8601String(),
        'dkp_base_points' => 10,
        'participants' => [
            ['character_id' => $ctx['char']->id],
        ],
    ]);

    expect((float) EventHistoryParticipant::query()
        ->where('event_history_id', $history->id)
        ->value('dkp_coefficient'))->toBe(2.5);
});

it('recalculates user dkp balance when event history base points change on update', function () {
    $ctx = seedGuildDkpContext();

    $history = app(\Domains\Event\Actions\CreateEventHistoryAction::class)([
        'guild_id' => $ctx['guild']->id,
        'title' => 'Raid',
        'occurred_at' => now()->toIso8601String(),
        'dkp_base_points' => 10,
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 1],
        ],
    ]);

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(10);

    app(\Domains\Event\Actions\UpdateEventHistoryAction::class)($history, [
        'dkp_base_points' => 20,
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 1],
        ],
    ]);

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(20);

    expect(GuildDkpLedgerEntry::query()
        ->where('event_history_id', $history->id)
        ->where('source', 'event')
        ->value('amount'))->toBe(20);
});

it('recalculates user dkp balance when participant coefficient changes on update', function () {
    $ctx = seedGuildDkpContext();

    $history = app(\Domains\Event\Actions\CreateEventHistoryAction::class)([
        'guild_id' => $ctx['guild']->id,
        'title' => 'Raid',
        'occurred_at' => now()->toIso8601String(),
        'dkp_base_points' => 10,
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 1],
        ],
    ]);

    app(\Domains\Event\Actions\UpdateEventHistoryAction::class)($history, [
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 2],
        ],
    ]);

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(20);
});

it('recalculates user dkp balance when override is applied on update', function () {
    $ctx = seedGuildDkpContext();

    $history = app(\Domains\Event\Actions\CreateEventHistoryAction::class)([
        'guild_id' => $ctx['guild']->id,
        'title' => 'Raid',
        'occurred_at' => now()->toIso8601String(),
        'dkp_base_points' => 10,
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 1],
        ],
    ]);

    app(\Domains\Event\Actions\UpdateEventHistoryAction::class)($history, [
        'participants' => [
            [
                'character_id' => $ctx['char']->id,
                'dkp_coefficient' => 1,
                'dkp_points_override' => 7,
            ],
        ],
    ]);

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(7);
});

it('clears user dkp from event when participant is removed on update', function () {
    $ctx = seedGuildDkpContext();

    $history = app(\Domains\Event\Actions\CreateEventHistoryAction::class)([
        'guild_id' => $ctx['guild']->id,
        'title' => 'Raid',
        'occurred_at' => now()->toIso8601String(),
        'dkp_base_points' => 10,
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 1],
        ],
    ]);

    app(\Domains\Event\Actions\UpdateEventHistoryAction::class)($history, [
        'participants' => [],
    ]);

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(0);

    expect(GuildDkpLedgerEntry::query()
        ->where('event_history_id', $history->id)
        ->where('source', 'event')
        ->exists())->toBeFalse();
});

it('distributes total event dkp among participants by coefficient', function () {
    $ctx = seedGuildDkpContext();

    $title = \Domains\Event\Models\EventHistoryTitle::query()->create([
        'name' => 'Pool raid',
        'dkp_base_points' => null,
        'distribute_dkp_to_participants' => true,
    ]);

    $otherChar = Character::query()->create([
        'user_id' => $ctx['user']->id,
        'game_id' => $ctx['guild']->game_id,
        'localization_id' => $ctx['guild']->localization_id,
        'server_id' => $ctx['guild']->server_id,
        'name' => 'Char B',
        'use_profile_avatar' => false,
        'is_main' => false,
    ]);
    GuildMember::query()->create([
        'guild_id' => $ctx['guild']->id,
        'character_id' => $otherChar->id,
        'joined_at' => now(),
        'dkp_coefficient' => 2,
    ]);

    $history = app(\Domains\Event\Actions\CreateEventHistoryAction::class)([
        'guild_id' => $ctx['guild']->id,
        'title' => $title->name,
        'occurred_at' => now()->toIso8601String(),
        'dkp_base_points' => 90,
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 1],
            ['character_id' => $otherChar->id, 'dkp_coefficient' => 2],
        ],
    ]);

    expect($history->distribute_dkp_to_participants)->toBeTrue();

    $ledgerAmounts = GuildDkpLedgerEntry::query()
        ->where('event_history_id', $history->id)
        ->where('source', 'event')
        ->orderBy('amount')
        ->pluck('amount')
        ->all();

    expect($ledgerAmounts)->toBe([30, 60]);
});

it('keeps dkp balance when only event title changes on update', function () {
    $ctx = seedGuildDkpContext();

    $history = app(\Domains\Event\Actions\CreateEventHistoryAction::class)([
        'guild_id' => $ctx['guild']->id,
        'title' => 'Raid',
        'occurred_at' => now()->toIso8601String(),
        'dkp_base_points' => 10,
        'participants' => [
            ['character_id' => $ctx['char']->id, 'dkp_coefficient' => 1],
        ],
    ]);

    app(\Domains\Event\Actions\UpdateEventHistoryAction::class)($history->fresh(), [
        'title' => 'Raid renamed',
    ]);

    expect(GuildUserDkpBalance::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('user_id', $ctx['user']->id)
        ->value('balance'))->toBe(10);
});
