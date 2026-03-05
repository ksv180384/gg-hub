<?php

namespace Domains\Guild\Actions;

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

        $member->guild_role_id = $guildRoleId;
        $member->save();
    }
}
