<?php

use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\Notification\Models\Notification;
use Domains\Raid\Actions\SetRaidCompositionAction;
use Domains\Raid\Models\Raid;
use Domains\Raid\Models\RaidApplication;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createRaidRecruitmentContext(): array
{
    $leaderUser = User::factory()->create();
    $applicantUser = User::factory()->create();
    $game = Game::query()->create([
        'name' => 'Raid Test Game',
        'slug' => 'raid-test-game',
        'is_active' => true,
    ]);
    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'ru',
        'name' => 'Русский',
        'is_active' => true,
    ]);
    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Raid Test Server',
        'slug' => 'raid-test-server',
        'is_active' => true,
    ]);
    $guild = Guild::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Raid Test Guild',
        'slug' => 'raid-test-guild',
        'owner_id' => $leaderUser->id,
        'is_recruiting' => false,
    ]);
    $leaderCharacter = Character::query()->create([
        'user_id' => $leaderUser->id,
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'RaidLeader',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);
    $applicantCharacter = Character::query()->create([
        'user_id' => $applicantUser->id,
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'RaidApplicant',
        'use_profile_avatar' => false,
        'is_main' => true,
    ]);

    foreach ([$leaderCharacter, $applicantCharacter] as $character) {
        GuildMember::query()->create([
            'guild_id' => $guild->id,
            'character_id' => $character->id,
            'joined_at' => now(),
        ]);
    }

    $raid = Raid::query()->create([
        'guild_id' => $guild->id,
        'leader_character_id' => $leaderCharacter->id,
        'created_by' => $leaderUser->id,
        'name' => 'Main Raid',
        'sort_order' => 0,
    ]);

    return compact(
        'leaderUser',
        'applicantUser',
        'guild',
        'leaderCharacter',
        'applicantCharacter',
        'raid',
    );
}

it('supports the complete raid application flow with notifications', function () {
    $context = createRaidRecruitmentContext();

    $context['raid']->members()->attach($context['leaderCharacter']->id, [
        'slot_index' => 0,
    ]);

    actingAs($context['leaderUser'])
        ->putJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/recruitment", [
            'is_recruiting' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('is_recruiting', true);

    $applicationResponse = actingAs($context['applicantUser'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/applications", [
            'character_id' => $context['applicantCharacter']->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', RaidApplication::STATUS_PENDING);

    actingAs($context['applicantUser'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/applications", [
            'character_id' => $context['applicantCharacter']->id,
        ])
        ->assertUnprocessable();

    expect(Notification::query()->where('user_id', $context['leaderUser']->id)->exists())->toBeTrue();

    $applicationId = $applicationResponse->json('data.id');
    actingAs($context['leaderUser'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/applications/{$applicationId}/accept")
        ->assertSuccessful()
        ->assertJsonPath('status', RaidApplication::STATUS_ACCEPTED);

    $member = $context['raid']->members()
        ->where('character_id', $context['applicantCharacter']->id)
        ->first();
    expect($member)->not()->toBeNull()
        ->and((int) $member->pivot->slot_index)->toBe(1)
        ->and($member->pivot->accepted_at)->not()->toBeNull();

    actingAs($context['leaderUser'])
        ->getJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}")
        ->assertSuccessful()
        ->assertJsonFragment([
            'character_id' => $context['applicantCharacter']->id,
            'accepted_at' => $member->pivot->accepted_at->toIso8601String(),
            'slot_index' => 1,
        ]);

    actingAs($context['leaderUser'])
        ->getJson("/api/v1/guilds/{$context['guild']->id}/raids")
        ->assertSuccessful()
        ->assertJsonPath('data.0.members_count', 2);

    $notification = Notification::query()
        ->where('user_id', $context['applicantUser']->id)
        ->latest('id')
        ->firstOrFail();
    expect((int) $notification->game_id)
        ->toBe((int) $context['guild']->game_id)
        ->and((int) $notification->guild_id)
        ->toBe((int) $context['guild']->id)
        ->and($notification->message)
        ->toContain('Принял(а): '.$context['leaderUser']->name);

    $updatedRaid = app(SetRaidCompositionAction::class)(
        $context['leaderUser'],
        $context['raid'],
        [[
            'character_id' => $context['leaderCharacter']->id,
            'slot_index' => 0,
        ]],
    );

    expect($updatedRaid->members)->toHaveCount(1);

    expect($context['raid']->members()
        ->whereKey($context['applicantCharacter']->id)
        ->exists())
        ->toBeFalse()
        ->and(RaidApplication::query()->findOrFail($applicationId)->status)
        ->toBe(RaidApplication::STATUS_REMOVED);

    actingAs($context['applicantUser'])
        ->getJson("/api/v1/guilds/{$context['guild']->id}/raids")
        ->assertSuccessful()
        ->assertJsonPath('data.0.my_applications.0.status', RaidApplication::STATUS_REMOVED);

    actingAs($context['applicantUser'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/applications", [
            'character_id' => $context['applicantCharacter']->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.id', $applicationId)
        ->assertJsonPath('data.status', RaidApplication::STATUS_PENDING);
});

it('includes guild, game and decider in a rejected raid application notification', function () {
    $context = createRaidRecruitmentContext();
    $context['raid']->update(['is_recruiting' => true]);

    $applicationId = actingAs($context['applicantUser'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/applications", [
            'character_id' => $context['applicantCharacter']->id,
        ])
        ->assertCreated()
        ->json('data.id');

    actingAs($context['leaderUser'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/applications/{$applicationId}/reject")
        ->assertSuccessful()
        ->assertJsonPath('status', RaidApplication::STATUS_REJECTED);

    $notification = Notification::query()
        ->where('user_id', $context['applicantUser']->id)
        ->latest('id')
        ->firstOrFail();

    expect((int) $notification->game_id)
        ->toBe((int) $context['guild']->game_id)
        ->and((int) $notification->guild_id)
        ->toBe((int) $context['guild']->id)
        ->and($notification->message)
        ->toContain('Заявка персонажа RaidApplicant')
        ->toContain('Отклонил(а): '.$context['leaderUser']->name);

    actingAs($context['applicantUser'])
        ->getJson('/api/v1/notifications')
        ->assertSuccessful()
        ->assertJsonPath('data.0.game_id', $context['guild']->game_id)
        ->assertJsonPath('data.0.guild_id', $context['guild']->id)
        ->assertJsonPath('data.0.game.name', 'Raid Test Game')
        ->assertJsonPath('data.0.guild.name', 'Raid Test Guild');

    actingAs($context['applicantUser'])
        ->postJson("/api/v1/guilds/{$context['guild']->id}/raids/{$context['raid']->id}/applications", [
            'character_id' => $context['applicantCharacter']->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.id', $applicationId)
        ->assertJsonPath('data.status', RaidApplication::STATUS_PENDING);

    $application = RaidApplication::query()->findOrFail($applicationId);
    expect($application->status)->toBe(RaidApplication::STATUS_PENDING)
        ->and($application->decided_by)->toBeNull()
        ->and($application->decided_at)->toBeNull()
        ->and(RaidApplication::query()
            ->where('raid_id', $context['raid']->id)
            ->where('character_id', $context['applicantCharacter']->id)
            ->count())
        ->toBe(1);
});

it('returns users grouped across child raids for a parent raid', function () {
    $context = createRaidRecruitmentContext();
    $parent = Raid::query()->create([
        'guild_id' => $context['guild']->id,
        'name' => 'Parent Raid',
        'sort_order' => 1,
    ]);
    $context['raid']->update(['parent_id' => $parent->id]);
    $context['raid']->members()->attach($context['applicantCharacter']->id, ['slot_index' => 0]);

    actingAs($context['leaderUser'])
        ->getJson("/api/v1/guilds/{$context['guild']->id}/raids/{$parent->id}/descendant-users")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $context['applicantUser']->id)
        ->assertJsonPath('data.0.characters.0.name', 'RaidApplicant')
        ->assertJsonPath('data.0.characters.0.raids.0.name', 'Main Raid');
});
