<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;
use Domains\Guild\Models\Guild;

class GetEventHistoryAction
{
    public function __invoke(Guild $guild, int $id): ?EventHistory
    {
        return EventHistory::query()
            ->where('guild_id', $guild->id)
            ->with([
                'participants.character:id,name',
                'screenshots',
            ])
            ->find($id);
    }
}

