<?php

namespace Domains\Poll\Actions;

use App\Filters\PollFilter;
use Domains\Poll\Models\Poll;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminPollsAction
{
    public function __construct(
        private CloseExpiredPollAction $closeExpiredPollAction
    ) {}

    public function __invoke(PollFilter $filter, int $perPage = 20): LengthAwarePaginator
    {
        $polls = Poll::query()
            ->with([
                'options' => fn ($query) => $query
                    ->withCount('votes')
                    ->with(['votes' => fn ($voteQuery) => $voteQuery->with('character:id,name')]),
                'creatorCharacter:id,name',
                'guild:id,name',
            ])
            ->filter($filter)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        foreach ($polls as $poll) {
            ($this->closeExpiredPollAction)($poll);
        }

        return $polls;
    }
}
