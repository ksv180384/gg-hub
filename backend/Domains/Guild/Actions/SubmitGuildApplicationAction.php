<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\Guild;

class SubmitGuildApplicationAction
{
    public function __invoke(Guild $guild, int $characterId, array $formData): GuildApplication
    {
        return GuildApplication::create([
            'guild_id' => $guild->id,
            'character_id' => $characterId,
            'form_data' => $formData,
            'status' => 'pending',
        ]);
    }
}
