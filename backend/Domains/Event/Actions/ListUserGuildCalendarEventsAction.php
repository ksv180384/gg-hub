<?php

namespace Domains\Event\Actions;

use App\Filters\EventFilter;
use Domains\Event\Models\Event;
use Domains\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ListUserGuildCalendarEventsAction
{
    public function __invoke(User $user, EventFilter $filter): Collection|LengthAwarePaginator
    {
        $guildIds = $user->guildIds();
        if (count($guildIds) === 0) {
            return new Collection([]);
        }

        return Event::query()
            ->whereIn('guild_id', $guildIds)
            ->with([
                'creator:id,name',
                'guild:id,game_id,name',
                'guild.game:id,name',
                'participants.character:id,name,user_id',
            ])
            ->filter($filter)
            ->orderBy('starts_at')
            ->get();
    }
}
