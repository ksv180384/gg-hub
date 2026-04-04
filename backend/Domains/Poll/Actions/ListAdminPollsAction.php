<?php

namespace Domains\Poll\Actions;

use Domains\Poll\Models\Poll;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminPollsAction
{
    public function __construct(
        private CloseExpiredPollAction $closeExpiredPollAction
    ) {}

    /**
     * Список всех голосований для админки с пагинацией.
     */
    public function __invoke(int $perPage = 20, ?int $guildId = null): LengthAwarePaginator
    {
        $query = Poll::query()
            ->with(['options' => fn ($q) => $q->withCount('votes')->with(['votes' => fn ($q2) => $q2->with('character:id,name')]), 'creatorCharacter:id,name', 'guild:id,name'])
            ->orderByDesc('created_at');

        if ($guildId !== null) {
            $query->where('guild_id', $guildId);
        }

        $polls = $query->paginate($perPage);

        foreach ($polls as $poll) {
            ($this->closeExpiredPollAction)($poll);
        }

        return $polls;
    }
}
