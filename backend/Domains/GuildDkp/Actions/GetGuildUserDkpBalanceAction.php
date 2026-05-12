<?php

namespace Domains\GuildDkp\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildDkp\Models\GuildUserDkpBalance;

class GetGuildUserDkpBalanceAction
{
    public function __invoke(Guild $guild, int $userId): int
    {
        return (int) (GuildUserDkpBalance::query()
            ->where('guild_id', $guild->id)
            ->where('user_id', $userId)
            ->value('balance') ?? 0);
    }
}
