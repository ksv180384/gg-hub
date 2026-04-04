<?php

namespace Domains\Poll\Actions;

use Domains\Guild\Models\Guild;
use Domains\Poll\Models\Poll;
use Illuminate\Database\Eloquent\Collection;

class ListGuildPollsAction
{
    public function __construct(
        private CloseExpiredPollAction $closeExpiredPollAction
    ) {}

    /**
     * @return Collection<int, Poll>
     */
    public function __invoke(Guild $guild): Collection
    {
        $polls = Poll::query()
            ->where('guild_id', $guild->id)
            ->with(['options' => fn ($q) => $q->withCount('votes')->with(['votes' => fn ($q2) => $q2->with('character:id,name')]), 'creatorCharacter:id,name'])
            ->orderByDesc('created_at')
            ->get();

        foreach ($polls as $poll) {
            ($this->closeExpiredPollAction)($poll);
        }

        return $polls;
    }
}
