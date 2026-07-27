<?php

use App\Models\Notification;
use App\Models\User;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\ConstantParty\Models\ConstantPartyStorageItemGrant;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'reason' => 'Raid',
        ])
        ->assertCreated()
        ->assertJsonPath('data.reason', 'Raid');

    expect(ConstantPartyStorageItemGrant::query()
        ->where('constant_party_id', $partyId)
        ->where('item_id', $itemId)
        ->count())->toBe(1);
});
