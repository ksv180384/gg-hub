<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\Event;
use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ListGuildEventsAction
{
    /**
     * События гильдии за период (пересечение с [from, to] по starts_at/ends_at).
     *
     * @param  array{from?: string, to?: string}  $params  from, to — даты в Y-m-d
     */
    public function __invoke(Guild $guild, array $params = []): Collection|LengthAwarePaginator
    {
        $query = Event::query()
            ->where('guild_id', $guild->id)
            ->with('creator:id,name')
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
