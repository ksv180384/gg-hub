<?php

namespace Domains\Event\Actions;

use App\Filters\EventFilter;
use Domains\Event\Models\Event;
use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ListGuildEventsAction
{
    public function __invoke(Guild $guild, EventFilter $filter): Collection|LengthAwarePaginator
    {
        return Event::query()
            ->where('guild_id', $guild->id)
            ->with(['creator:id,name', 'participants.character:id,name,user_id'])
            ->filter($filter)
            ->orderBy('starts_at')
            ->get();
    }
}
