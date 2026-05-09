<?php

use App\Models\User;
use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\GuildRole;
use Domains\Access\Models\Permission;
use Domains\Character\Models\Character;
use Domains\Event\Models\EventHistory;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function seedMinimalGuildContext(): array
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

    return compact('user', 'game', 'loc', 'server', 'guild', 'char');
}

it('allows guild leader to create item and grant it', function () {
    $ctx = seedMinimalGuildContext();

    // лидер гильдии получает все guild-scope права по логике проекта
    $ctx['guild']->update(['leader_character_id' => $ctx['char']->id]);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/items", [
            'name' => 'Sword of Test',
            'tier' => '2',
            'color' => '#ff0000',
            'dkp_cost' => 10,
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Sword of Test']);

    $itemId = GuildBankItem::query()->where('guild_id', $ctx['guild']->id)->value('id');
    expect($itemId)->not()->toBeNull();

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants", [
            'guild_bank_item_id' => $itemId,
            'received_by_character_id' => $ctx['char']->id,
            'reason' => 'За вклад в рейд',
        ])
        ->assertCreated()
        ->assertJsonFragment(['reason' => 'За вклад в рейд']);

    expect(GuildBankItemGrant::query()->where('guild_id', $ctx['guild']->id)->count())->toBe(1);
});

it('revokes grant and restores item quantity with permission', function () {
    $ctx = seedMinimalGuildContext();
    $ctx['guild']->update(['leader_character_id' => $ctx['char']->id]);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/items", [
            'name' => 'Limited Sword',
            'quantity' => 2,
        ])
        ->assertCreated();

    $item = GuildBankItem::query()->where('guild_id', $ctx['guild']->id)->first();
    expect($item)->not()->toBeNull();
    expect($item->quantity)->toBe(2);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants", [
            'guild_bank_item_id' => $item->id,
            'received_by_character_id' => $ctx['char']->id,
            'reason' => 'Тест',
        ])
        ->assertCreated();

    $item->refresh();
    expect($item->quantity)->toBe(1);

    $grantId = GuildBankItemGrant::query()->where('guild_id', $ctx['guild']->id)->value('id');
    expect($grantId)->not()->toBeNull();

    actingAs($ctx['user'])
        ->deleteJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants/{$grantId}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $grantId)
        ->assertJsonFragment(['message' => 'Выдача отменена.']);

    $item->refresh();
    expect($item->quantity)->toBe(2);

    expect(GuildBankItemGrant::query()->whereKey($grantId)->exists())->toBeFalse();
});

it('forbids revoking grant without peredavat permission', function () {
    $ctx = seedMinimalGuildContext();
    $role = GuildRole::query()->create([
        'guild_id' => $ctx['guild']->id,
        'name' => 'Member',
        'slug' => 'member',
    ]);
    GuildMember::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('character_id', $ctx['char']->id)
        ->update(['guild_role_id' => $role->id]);

    $item = GuildBankItem::query()->create([
        'guild_id' => $ctx['guild']->id,
        'name' => 'Item',
        'description' => null,
        'tier' => null,
        'color' => null,
        'dkp_cost' => null,
        'quantity' => null,
    ]);
    $grant = GuildBankItemGrant::query()->create([
        'guild_id' => $ctx['guild']->id,
        'guild_bank_item_id' => $item->id,
        'received_by_character_id' => $ctx['char']->id,
        'granted_by_character_id' => null,
        'reason' => 'x',
        'granted_at' => now(),
    ]);

    actingAs($ctx['user'])
        ->deleteJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants/{$grant->id}")
        ->assertForbidden();
});

it('removes grant from member bank list after revoke', function () {
    $ctx = seedMinimalGuildContext();
    $ctx['guild']->update(['leader_character_id' => $ctx['char']->id]);

    $item = GuildBankItem::query()->create([
        'guild_id' => $ctx['guild']->id,
        'name' => 'Item',
        'description' => null,
        'tier' => null,
        'color' => null,
        'dkp_cost' => null,
        'quantity' => null,
    ]);
    $grant = GuildBankItemGrant::query()->create([
        'guild_id' => $ctx['guild']->id,
        'guild_bank_item_id' => $item->id,
        'received_by_character_id' => $ctx['char']->id,
        'granted_by_character_id' => null,
        'reason' => 'active',
        'granted_at' => now()->subHour(),
    ]);

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/bank/members/{$ctx['char']->id}/grants")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reason', 'active');

    actingAs($ctx['user'])
        ->deleteJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants/{$grant->id}")
        ->assertSuccessful();

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/bank/members/{$ctx['char']->id}/grants")
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('forbids deleting bank item while it has active grants', function () {
    $ctx = seedMinimalGuildContext();
    $ctx['guild']->update(['leader_character_id' => $ctx['char']->id]);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/items", ['name' => 'To delete'])
        ->assertCreated();

    $itemId = GuildBankItem::query()->where('guild_id', $ctx['guild']->id)->value('id');
    expect($itemId)->not()->toBeNull();

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants", [
            'guild_bank_item_id' => $itemId,
            'received_by_character_id' => $ctx['char']->id,
            'reason' => 'x',
        ])
        ->assertCreated();

    actingAs($ctx['user'])
        ->deleteJson("/api/v1/guilds/{$ctx['guild']->id}/bank/items/{$itemId}")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Нельзя удалить предмет: у него есть активные выдачи. Сначала отмените выдачи в истории.']);

    expect(GuildBankItem::query()->whereKey($itemId)->exists())->toBeTrue();
});

it('allows deleting bank item after active grants are revoked', function () {
    $ctx = seedMinimalGuildContext();
    $ctx['guild']->update(['leader_character_id' => $ctx['char']->id]);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/items", ['name' => 'Revoke then delete'])
        ->assertCreated();

    $item = GuildBankItem::query()->where('guild_id', $ctx['guild']->id)->first();
    expect($item)->not()->toBeNull();

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants", [
            'guild_bank_item_id' => $item->id,
            'received_by_character_id' => $ctx['char']->id,
            'reason' => 'x',
        ])
        ->assertCreated();

    $grantId = GuildBankItemGrant::query()->where('guild_bank_item_id', $item->id)->value('id');
    actingAs($ctx['user'])
        ->deleteJson("/api/v1/guilds/{$ctx['guild']->id}/bank/grants/{$grantId}")
        ->assertSuccessful();

    actingAs($ctx['user'])
        ->deleteJson("/api/v1/guilds/{$ctx['guild']->id}/bank/items/{$item->id}")
        ->assertSuccessful();

    expect(GuildBankItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

it('forbids creating item without guild permission (not leader)', function () {
    $ctx = seedMinimalGuildContext();

    // создадим роль без прав и назначим её пользователю как участнику (лидер не задан)
    $role = GuildRole::query()->create([
        'guild_id' => $ctx['guild']->id,
        'name' => 'Member',
        'slug' => 'member',
    ]);
    GuildMember::query()
        ->where('guild_id', $ctx['guild']->id)
        ->where('character_id', $ctx['char']->id)
        ->update(['guild_role_id' => $role->id]);

    actingAs($ctx['user'])
        ->postJson("/api/v1/guilds/{$ctx['guild']->id}/bank/items", [
            'name' => 'Forbidden item',
            'tier' => '1',
        ])
        ->assertForbidden();
});

it('returns dkp fields for event history when dkp is enabled', function () {
    $ctx = seedMinimalGuildContext();
    $ctx['guild']->update(['leader_character_id' => $ctx['char']->id]);

    /** @var EventHistory $history */
    $history = EventHistory::query()->create([
        'guild_id' => $ctx['guild']->id,
        'event_history_title_id' => null,
        'description' => null,
        'occurred_at' => now(),
        'dkp_base_points' => 10,
    ]);

    actingAs($ctx['user'])
        ->getJson("/api/v1/guilds/{$ctx['guild']->id}/event-history/{$history->id}")
        ->assertSuccessful()
        ->assertJsonPath('dkp.base_points', 10);
});

