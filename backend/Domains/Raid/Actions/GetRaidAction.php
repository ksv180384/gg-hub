<?php

namespace Domains\Raid\Actions;

use Domains\Guild\Models\Guild;
use Domains\Raid\Models\Raid;

class GetRaidAction
{
    public function __invoke(Guild $guild, int $raidId): ?Raid
    {
        return Raid::query()
            ->where('guild_id', $guild->id)
            ->where('id', $raidId)
            ->with(['leader:id,name', 'parent:id,name,parent_id', 'creator:id,name'])
            ->first();
    }
}
