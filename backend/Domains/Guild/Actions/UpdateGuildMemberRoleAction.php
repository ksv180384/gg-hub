<?php

namespace Domains\Guild\Actions;

use Domains\Access\Models\GuildRole;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Validation\ValidationException;

/**
 * Обновляет роль участника гильдии (по character_id).
 * Требуется право meniat-izieniat-polzovateliu-rol.
 */
final class UpdateGuildMemberRoleAction
{
    public function __invoke(Guild $guild, int $characterId, int $guildRoleId): void
    {
        $member = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'character_id' => ['Участник не найден в гильдии.'],
            ]);
        }

        if ($guild->leader_character_id && (int) $guild->leader_character_id === (int) $characterId) {
            throw ValidationException::withMessages([
                'guild_role_id' => ['Роль лидера гильдии меняется только при смене лидера в настройках гильдии.'],
            ]);
        }

        $role = GuildRole::query()
            ->where('guild_id', $guild->id)
            ->whereKey($guildRoleId)
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'guild_role_id' => ['Указанная роль не найдена в этой гильдии.'],
            ]);
        }

        if ($role->slug === 'leader') {
            throw ValidationException::withMessages([
                'guild_role_id' => ['Роль «Лидер» назначается только при смене лидера гильдии в настройках.'],
            ]);
        }

        $member->guild_role_id = $guildRoleId;
        $member->save();
    }
}
