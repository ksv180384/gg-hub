<?php

use App\Actions\Server\MergeServersAction;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyChatMessage;
use Domains\ConstantParty\Models\ConstantPartyFormerMember;
use Domains\ConstantParty\Models\ConstantPartyInvitation;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\ConstantParty\Models\ConstantPartyStorageItem;
use Domains\ConstantParty\Models\ConstantPartyStorageItemGrant;
use Domains\ConstantParty\Models\ConstantPartyStorageItemTier;
use Domains\ConstantParty\Models\ConstantPartyStorageLog;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Notification\Models\Notification;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function seedConstantPartyContext(): array
{
    $leaderUser = User::factory()->create();
    $memberUser = User::factory()->create();

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
        'name' => 'Server A',
        'slug' => 'server-a',
        'is_active' => true,
    ]);
    $otherServer = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'name' => 'Server B',
        'slug' => 'server-b',
        'is_active' => true,
    ]);

    $leader = Character::query()->create([
        'user_id' => $leaderUser->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Leader',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    $member = Character::query()->create([
        'user_id' => $memberUser->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $server->id,
        'name' => 'Member',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    $otherServerCharacter = Character::query()->create([
        'user_id' => $memberUser->id,
        'game_id' => $game->id,
        'localization_id' => $loc->id,
        'server_id' => $otherServer->id,
        'name' => 'Other Server',
        'use_profile_avatar' => false,
        'is_main' => false,
    ]);

    return compact('leaderUser', 'memberUser', 'game', 'loc', 'server', 'otherServer', 'leader', 'member', 'otherServerCharacter');
}

it('creates constant party with selected character as leader', function () {
    $ctx = seedConstantPartyContext();

    $response = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Static Squad')
        ->assertJsonPath('data.leader_character_id', $ctx['leader']->id);

    $partyId = $response->json('data.id');

    expect(ConstantPartyMember::query()
        ->where('constant_party_id', $partyId)
        ->where('character_id', $ctx['leader']->id)
        ->where('role', ConstantPartyMember::ROLE_LEADER)
        ->where('can_manage_storage', true)
        ->exists())->toBeTrue();
});

it('does not invite character from another server', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/invitations", [
            'character_id' => $ctx['otherServerCharacter']->id,
        ])
        ->assertUnprocessable();
});

it('searches invite candidates only on constant party server', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/invitations/candidates?query=Member")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ctx['member']->id);

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/invitations/candidates?query=Other")
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('accepts invitation and creates member notification', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    $invitationId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/invitations", [
            'character_id' => $ctx['member']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    expect(Notification::query()->where('user_id', $ctx['memberUser']->id)->count())->toBe(1);

    actingAs($ctx['memberUser'])
        ->postJson("/api/v1/constant-parties/invitations/{$invitationId}/accept")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'accepted');

    expect(Notification::query()->where('user_id', $ctx['leaderUser']->id)->count())->toBe(1);

    expect(ConstantPartyMember::query()
        ->where('constant_party_id', $partyId)
        ->where('character_id', $ctx['member']->id)
        ->exists())->toBeTrue();
});

it('notifies inviter when invitation is declined', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    $invitationId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/invitations", [
            'character_id' => $ctx['member']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['memberUser'])
        ->postJson("/api/v1/constant-parties/invitations/{$invitationId}/decline")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'declined');

    expect(Notification::query()->where('user_id', $ctx['leaderUser']->id)->count())->toBe(1);
    expect(ConstantPartyMember::query()
        ->where('constant_party_id', $partyId)
        ->where('character_id', $ctx['member']->id)
        ->exists())->toBeFalse();
});

it('allows storage manager to add and grant item', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    ConstantPartyMember::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['member']->id,
        'role' => ConstantPartyMember::ROLE_MEMBER,
        'can_manage_storage' => false,
        'joined_at' => now(),
    ]);

    $itemId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/items", [
            'name' => 'Sword',
            'quantity' => 1,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/grants", [
            'item_id' => $itemId,
            'received_by_character_id' => $ctx['member']->id,
            'granted_by_character_id' => $ctx['leader']->id,
            'quantity' => 1,
            'reason' => 'Raid',
        ])
        ->assertCreated()
        ->assertJsonPath('data.reason', 'Raid');

    expect(ConstantPartyStorageItemGrant::query()
        ->where('constant_party_id', $partyId)
        ->where('item_id', $itemId)
        ->count())->toBe(1);
});

it('allows storage manager to edit item name and quantity', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    $itemId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/items", [
            'name' => 'Sword',
            'quantity' => 3,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->patchJson("/api/v1/constant-parties/{$partyId}/storage/items/{$itemId}", [
            'name' => 'Great Sword',
            'quantity' => 12,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Great Sword')
        ->assertJsonPath('data.quantity', 12);

    actingAs($ctx['leaderUser'])
        ->patchJson("/api/v1/constant-parties/{$partyId}/storage/items/{$itemId}", [
            'name' => 'Great Sword',
            'quantity' => null,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.quantity', null);

    expect(ConstantPartyStorageItem::query()->findOrFail($itemId))
        ->name->toBe('Great Sword')
        ->quantity->toBeNull();
});

it('records and lists constant party storage logs', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    ConstantPartyMember::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['member']->id,
        'role' => ConstantPartyMember::ROLE_MEMBER,
        'can_manage_storage' => false,
        'joined_at' => now(),
    ]);

    $itemId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/items", [
            'name' => 'Sword',
            'quantity' => 3,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->patchJson("/api/v1/constant-parties/{$partyId}/storage/items/{$itemId}", [
            'name' => 'Great Sword',
            'quantity' => 7,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertSuccessful();

    $deletedItemId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/items", [
            'name' => 'Temporary Item',
            'quantity' => 2,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->deleteJson("/api/v1/constant-parties/{$partyId}/storage/items/{$deletedItemId}")
        ->assertNoContent();

    actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/grants", [
            'item_id' => $itemId,
            'received_by_character_id' => $ctx['member']->id,
            'granted_by_character_id' => $ctx['leader']->id,
            'quantity' => 2,
            'reason' => 'Raid reward',
        ])
        ->assertCreated()
        ->assertJsonPath('data.quantity', 2);

    actingAs($ctx['memberUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/storage/logs")
        ->assertSuccessful()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0.action', ConstantPartyStorageLog::ACTION_ITEM_GRANTED)
        ->assertJsonPath('data.0.item_name', 'Great Sword')
        ->assertJsonPath('data.0.actor_character_name', 'Leader')
        ->assertJsonPath('data.0.recipient_character_name', 'Member')
        ->assertJsonPath('data.0.old_value.quantity', 7)
        ->assertJsonPath('data.0.new_value.quantity', 5)
        ->assertJsonPath('data.0.metadata.quantity', 2)
        ->assertJsonPath('data.1.action', ConstantPartyStorageLog::ACTION_ITEM_DELETED)
        ->assertJsonPath('data.1.item_id', null)
        ->assertJsonPath('data.1.item_name', 'Temporary Item')
        ->assertJsonPath('data.3.action', ConstantPartyStorageLog::ACTION_QUANTITY_CHANGED)
        ->assertJsonPath('data.3.old_value.quantity', 3)
        ->assertJsonPath('data.3.new_value.quantity', 7)
        ->assertJsonPath('data.4.action', ConstantPartyStorageLog::ACTION_ITEM_RENAMED)
        ->assertJsonPath('data.4.old_value.name', 'Sword')
        ->assertJsonPath('data.4.new_value.name', 'Great Sword');

    expect(ConstantPartyStorageLog::query()
        ->where('constant_party_id', $partyId)
        ->count())->toBe(7);
});

it('records joining, leaving, and removing constant party members', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    $invitationId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/invitations", [
            'character_id' => $ctx['member']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['memberUser'])
        ->postJson("/api/v1/constant-parties/invitations/{$invitationId}/accept")
        ->assertSuccessful();

    $joinMessage = 'Персонаж Member вступил в КП «Static Squad». Инициатор: Leader.';
    expect(Notification::query()->where('message', $joinMessage)->count())->toBe(2)
        ->and(Notification::query()
            ->where('user_id', $ctx['leaderUser']->id)
            ->where('message', $joinMessage)
            ->exists())->toBeTrue()
        ->and(Notification::query()
            ->where('user_id', $ctx['memberUser']->id)
            ->where('message', $joinMessage)
            ->exists())->toBeTrue();

    Notification::query()->delete();

    $member = ConstantPartyMember::query()
        ->where('constant_party_id', $partyId)
        ->where('character_id', $ctx['member']->id)
        ->firstOrFail();

    actingAs($ctx['memberUser'])
        ->deleteJson("/api/v1/constant-parties/{$partyId}/members/{$member->id}")
        ->assertNoContent();

    $leaveMessage = 'Персонаж Member покинул КП «Static Squad». Инициатор: Member.';
    expect(Notification::query()->where('message', $leaveMessage)->count())->toBe(2)
        ->and(Notification::query()
            ->where('user_id', $ctx['memberUser']->id)
            ->where('message', $leaveMessage)
            ->where('link', '/my-constant-parties')
            ->exists())->toBeTrue();

    Notification::query()->delete();

    $member = ConstantPartyMember::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['member']->id,
        'role' => ConstantPartyMember::ROLE_MEMBER,
        'can_manage_storage' => false,
        'joined_at' => now(),
    ]);

    actingAs($ctx['leaderUser'])
        ->deleteJson("/api/v1/constant-parties/{$partyId}/members/{$member->id}")
        ->assertNoContent();

    $removeMessage = 'Персонаж Member исключён из КП «Static Squad». Инициатор: Leader.';
    expect(Notification::query()->where('message', $removeMessage)->count())->toBe(2)
        ->and(Notification::query()
            ->where('user_id', $ctx['memberUser']->id)
            ->where('message', $removeMessage)
            ->where('link', '/my-constant-parties')
            ->exists())->toBeTrue();

    $logs = ConstantPartyStorageLog::query()
        ->where('constant_party_id', $partyId)
        ->orderByDesc('id')
        ->get();

    expect($logs)->toHaveCount(4)
        ->and($logs[0]->action)->toBe(ConstantPartyStorageLog::ACTION_MEMBER_REMOVED)
        ->and($logs[0]->actor_character_name)->toBe('Leader')
        ->and($logs[0]->recipient_character_name)->toBe('Member')
        ->and($logs[1]->action)->toBe(ConstantPartyStorageLog::ACTION_MEMBER_LEFT)
        ->and($logs[1]->actor_character_name)->toBe('Member')
        ->and($logs[2]->action)->toBe(ConstantPartyStorageLog::ACTION_MEMBER_JOINED)
        ->and($logs[2]->recipient_character_name)->toBe('Member')
        ->and($logs[3]->action)->toBe(ConstantPartyStorageLog::ACTION_MEMBER_JOINED)
        ->and($logs[3]->recipient_character_name)->toBe('Leader');
});

it('filters, sorts, and paginates fifty constant party logs', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    foreach (range(1, 55) as $index) {
        ConstantPartyStorageLog::query()->forceCreate([
            'constant_party_id' => $partyId,
            'actor_character_id' => $ctx['leader']->id,
            'action' => ConstantPartyStorageLog::ACTION_QUANTITY_CHANGED,
            'item_name' => "Item {$index}",
            'actor_character_name' => 'Leader',
            'old_value' => ['quantity' => $index - 1],
            'new_value' => ['quantity' => $index],
            'created_at' => now()->setDate(2030, 1, 1)->startOfDay()->addMinutes($index),
        ]);
    }

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/storage/logs")
        ->assertSuccessful()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.item_name', 'Item 55')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 50);

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/storage/logs?date_from=2030-01-01&date_to=2030-01-01&sort=asc&page=2")
        ->assertSuccessful()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.item_name', 'Item 51')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2);
});

it('keeps item history available after member leaves constant party', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    $member = ConstantPartyMember::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['member']->id,
        'role' => ConstantPartyMember::ROLE_MEMBER,
        'can_manage_storage' => false,
        'joined_at' => now()->subDay(),
    ]);

    $itemId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/items", [
            'name' => 'Sword',
            'quantity' => 1,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/grants", [
            'item_id' => $itemId,
            'received_by_character_id' => $ctx['member']->id,
            'granted_by_character_id' => $ctx['leader']->id,
            'quantity' => 1,
            'reason' => 'Raid',
        ])
        ->assertCreated();

    actingAs($ctx['leaderUser'])
        ->deleteJson("/api/v1/constant-parties/{$partyId}/members/{$member->id}")
        ->assertNoContent();

    expect(ConstantPartyFormerMember::query()
        ->where('constant_party_id', $partyId)
        ->where('character_id', $ctx['member']->id)
        ->exists())->toBeTrue();

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/storage/former-members")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.character.name', 'Member');

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/storage/characters/{$ctx['member']->id}/grants")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.item.name', 'Sword')
        ->assertJsonPath('data.0.reason', 'Raid');

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/storage/characters/{$ctx['otherServerCharacter']->id}/grants")
        ->assertNotFound();
});

it('moves constant party to target server and preserves its storage when servers are merged', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    $invitationId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/invitations", [
            'character_id' => $ctx['member']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    $itemId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/storage/items", [
            'name' => 'Ancient Sword',
            'quantity' => 1,
            'actor_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    app(MergeServersAction::class)(
        $ctx['game'],
        $ctx['loc'],
        $ctx['otherServer']->id,
        [$ctx['server']->id],
    );

    expect($ctx['leader']->fresh()->server_id)->toBe($ctx['otherServer']->id)
        ->and(ConstantParty::query()->findOrFail($partyId)->server_id)->toBe($ctx['otherServer']->id)
        ->and(ConstantPartyMember::query()
            ->where('constant_party_id', $partyId)
            ->where('character_id', $ctx['leader']->id)
            ->exists())->toBeTrue()
        ->and(ConstantPartyStorageItem::query()
            ->where('id', $itemId)
            ->where('constant_party_id', $partyId)
            ->exists())->toBeTrue();

    actingAs($ctx['memberUser'])
        ->postJson("/api/v1/constant-parties/invitations/{$invitationId}/accept")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'accepted');

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$partyId}/invitations/candidates?query=Other")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ctx['otherServerCharacter']->id);
});
it('lists only parties and invitations of selected game', function () {
    $ctx = seedConstantPartyContext();

    $firstPartyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'First Game Party',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    $secondGame = Game::query()->create([
        'name' => 'Second Game',
        'slug' => 'second-game',
        'is_active' => true,
    ]);
    $secondLoc = Localization::query()->create([
        'game_id' => $secondGame->id,
        'code' => 'eu',
        'name' => 'Europe',
        'is_active' => true,
    ]);
    $secondServer = Server::query()->create([
        'game_id' => $secondGame->id,
        'localization_id' => $secondLoc->id,
        'name' => 'Second Server',
        'slug' => 'second-server',
        'is_active' => true,
    ]);
    $secondLeader = Character::query()->create([
        'user_id' => $ctx['leaderUser']->id,
        'game_id' => $secondGame->id,
        'localization_id' => $secondLoc->id,
        'server_id' => $secondServer->id,
        'name' => 'Second Leader',
        'use_profile_avatar' => false,
        'is_main' => false,
    ]);
    $secondMember = Character::query()->create([
        'user_id' => $ctx['memberUser']->id,
        'game_id' => $secondGame->id,
        'localization_id' => $secondLoc->id,
        'server_id' => $secondServer->id,
        'name' => 'Second Member',
        'use_profile_avatar' => false,
        'is_main' => false,
    ]);

    $secondPartyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $secondGame->id,
            'name' => 'Second Game Party',
            'leader_character_id' => $secondLeader->id,
        ])
        ->assertCreated()
        ->json('data.id');

    $firstInvitationId = actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$firstPartyId}/invitations", [
            'character_id' => $ctx['member']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$secondPartyId}/invitations", [
            'character_id' => $secondMember->id,
        ])
        ->assertCreated();

    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties?game_id={$ctx['game']->id}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $firstPartyId);

    actingAs($ctx['memberUser'])
        ->getJson("/api/v1/constant-parties?game_id={$ctx['game']->id}")
        ->assertSuccessful()
        ->assertJsonCount(1, 'invitations')
        ->assertJsonPath('invitations.0.id', $firstInvitationId);

    actingAs($ctx['leaderUser'])
        ->withHeader('X-Site-Host', 'test-game.gg-hub.local')
        ->getJson("/api/v1/constant-parties?game_id={$secondGame->id}")
        ->assertNotFound();
    actingAs($ctx['leaderUser'])
        ->getJson("/api/v1/constant-parties/{$secondPartyId}?game_id={$ctx['game']->id}")
        ->assertNotFound();

    actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Wrong Game Party',
            'leader_character_id' => $secondLeader->id,
        ])
        ->assertNotFound();
});

it('transfers constant party leadership atomically', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    $oldLeaderMember = ConstantPartyMember::query()
        ->where('constant_party_id', $partyId)
        ->where('character_id', $ctx['leader']->id)
        ->firstOrFail();

    $newLeaderMember = ConstantPartyMember::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['member']->id,
        'role' => ConstantPartyMember::ROLE_MEMBER,
        'can_manage_storage' => false,
        'joined_at' => now(),
    ]);

    actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/members/{$newLeaderMember->id}/transfer-leadership")
        ->assertSuccessful()
        ->assertJsonPath('data.leader_character_id', $ctx['member']->id)
        ->assertJsonPath('data.my_member.role', ConstantPartyMember::ROLE_MEMBER)
        ->assertJsonPath('data.my_member.can_manage_storage', false);

    expect(ConstantParty::query()->findOrFail($partyId)->leader_character_id)
        ->toBe($ctx['member']->id)
        ->and($oldLeaderMember->fresh()->role)
        ->toBe(ConstantPartyMember::ROLE_MEMBER)
        ->and($oldLeaderMember->fresh()->can_manage_storage)
        ->toBeFalse()
        ->and($newLeaderMember->fresh()->role)
        ->toBe(ConstantPartyMember::ROLE_LEADER)
        ->and($newLeaderMember->fresh()->can_manage_storage)
        ->toBeTrue();

    expect(Notification::query()
        ->where('user_id', $ctx['memberUser']->id)
        ->where('link', "/constant-parties/{$partyId}")
        ->exists())->toBeTrue();

    actingAs($ctx['leaderUser'])
        ->postJson("/api/v1/constant-parties/{$partyId}/members/{$newLeaderMember->id}/transfer-leadership")
        ->assertForbidden();

    actingAs($ctx['memberUser'])
        ->patchJson("/api/v1/constant-parties/{$partyId}/members/{$oldLeaderMember->id}", [
            'can_manage_storage' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.can_manage_storage', true);
});

it('allows only the leader to dissolve a party and permanently deletes all related data', function () {
    $ctx = seedConstantPartyContext();

    $partyId = actingAs($ctx['leaderUser'])
        ->postJson('/api/v1/constant-parties', [
            'game_id' => $ctx['game']->id,
            'name' => 'Static Squad',
            'leader_character_id' => $ctx['leader']->id,
        ])
        ->json('data.id');

    $member = ConstantPartyMember::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['member']->id,
        'role' => ConstantPartyMember::ROLE_MEMBER,
        'can_manage_storage' => false,
        'joined_at' => now(),
    ]);
    ConstantPartyInvitation::query()->create([
        'constant_party_id' => $partyId,
        'invited_character_id' => $ctx['otherServerCharacter']->id,
        'invited_by_character_id' => $ctx['leader']->id,
        'status' => ConstantPartyInvitation::STATUS_PENDING,
    ]);
    ConstantPartyChatMessage::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['leader']->id,
        'body' => 'Party message',
    ]);
    $tier = ConstantPartyStorageItemTier::query()->create([
        'constant_party_id' => $partyId,
        'name' => 'Rare',
        'sort_order' => 1,
    ]);
    $item = ConstantPartyStorageItem::query()->create([
        'constant_party_id' => $partyId,
        'tier_id' => $tier->id,
        'name' => 'Crystal',
        'quantity' => 8,
        'created_by_character_id' => $ctx['leader']->id,
    ]);
    ConstantPartyStorageItemGrant::query()->create([
        'constant_party_id' => $partyId,
        'item_id' => $item->id,
        'received_by_character_id' => $ctx['member']->id,
        'granted_by_character_id' => $ctx['leader']->id,
        'quantity' => 2,
        'granted_at' => now(),
    ]);
    ConstantPartyFormerMember::query()->create([
        'constant_party_id' => $partyId,
        'character_id' => $ctx['otherServerCharacter']->id,
        'joined_at' => now()->subDay(),
        'left_at' => now(),
    ]);

    Notification::query()->delete();

    actingAs($ctx['memberUser'])
        ->deleteJson("/api/v1/constant-parties/{$partyId}")
        ->assertForbidden();

    expect(ConstantParty::query()->whereKey($partyId)->exists())->toBeTrue();

    actingAs($ctx['leaderUser'])
        ->deleteJson("/api/v1/constant-parties/{$partyId}")
        ->assertNoContent();

    expect(ConstantParty::withTrashed()->whereKey($partyId)->exists())->toBeFalse();

    foreach ([
        'constant_party_members',
        'constant_party_invitations',
        'constant_party_chat_messages',
        'constant_party_storage_item_tiers',
        'constant_party_storage_items',
        'constant_party_storage_item_grants',
        'constant_party_former_members',
        'constant_party_storage_logs',
    ] as $table) {
        expect(DB::table($table)->where('constant_party_id', $partyId)->exists())->toBeFalse();
    }

    $notifications = Notification::query()
        ->whereIn('user_id', [$ctx['leaderUser']->id, $ctx['memberUser']->id])
        ->get();

    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$ctx['leaderUser']->id, $ctx['memberUser']->id])->sort()->values()->all())
        ->and($notifications->every(fn (Notification $notification): bool => str_contains($notification->message, 'КП «Static Squad» распущена')
            && str_contains($notification->message, 'Инициатор: Leader')
            && $notification->link === '/my-constant-parties'
        ))->toBeTrue();
});
