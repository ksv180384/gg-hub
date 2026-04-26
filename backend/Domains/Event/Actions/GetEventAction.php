<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\Event;
use Domains\Guild\Models\Guild;

class GetEventAction
{
    public function __invoke(Guild $guild, int $eventId): ?Event
    {
        return Event::query()
            ->where('guild_id', $guild->id)
            ->where('id', $eventId)
            ->with(['creator:id,name', 'participants.character:id,name,user_id'])
            ->first();
    }
}
