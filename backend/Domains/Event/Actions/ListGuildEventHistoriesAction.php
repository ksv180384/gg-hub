<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;
use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ListGuildEventHistoriesAction
{
    /**
     * История событий гильдии, новые сверху.
     *
     * @param  array{per_page?: int|null}  $params
     */
    public function __invoke(Guild $guild, array $params = []): Collection|LengthAwarePaginator
    {
        $query = EventHistory::query()
            ->where('guild_id', $guild->id)
            ->with([
                'participants.character:id,name',
                'screenshots',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $perPage = $params['per_page'] ?? null;

        if ($perPage !== null && $perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }
}

