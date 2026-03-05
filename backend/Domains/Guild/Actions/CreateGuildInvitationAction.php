<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;

class CreateGuildInvitationAction
{
    /**
     * @param int $invitedByCharacterId ID персонажа-участника гильдии с правом приглашения
     */
    public function __invoke(Guild $guild, int $characterId, int $invitedByCharacterId): GuildApplication
    {
        return GuildApplication::create([
            'guild_id' => $guild->id,
            'character_id' => $characterId,
            'invited_by_character_id' => $invitedByCharacterId,
            'form_data' => [],
            'status' => 'invitation',
        ]);
    }
}
