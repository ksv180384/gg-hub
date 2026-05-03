<?php

namespace Domains\Event\Actions;

use App\Models\User;
use Domains\Event\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ListUserGuildCalendarEventsAction
{
    /**
     * События календаря по всем гильдиям пользователя за период.
     *
     * @param  array{from?: string, to?: string}  $params  from, to — даты в Y-m-d
     */
    public function __invoke(User $user, array $params = []): Collection|LengthAwarePaginator
    {
        $guildIds = $user->guildIds();
        if (count($guildIds) === 0) {
            return new Collection([]);
        }

        $query = Event::query()
            ->whereIn('guild_id', $guildIds)
            ->with([
                'creator:id,name',
                'guild:id,game_id,name',
                'guild.game:id,name',
                'participants.character:id,name,user_id',
            ])
            ->orderBy('starts_at');

        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;

        // Событие пересекается с [from, to]: starts_at <= to AND (ends_at >= from OR ends_at IS NULL)
        // Для повторяющихся: recurrence_ends_at >= from OR recurrence_ends_at IS NULL
        if ($from) {
            $query->where(function ($q) use ($from) {
                $q->whereDate('ends_at', '>=', $from)->orWhereNull('ends_at');
            });
            $query->where(function ($q) use ($from) {
                $q->whereDate('recurrence_ends_at', '>=', $from)->orWhereNull('recurrence_ends_at');
            });
        }
        if ($to) {
            $query->whereDate('starts_at', '<=', $to);
        }

        return $query->get();
    }
}

