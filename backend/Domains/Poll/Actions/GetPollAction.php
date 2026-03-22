<?php

namespace Domains\Poll\Actions;

use Domains\Guild\Models\Guild;
use Domains\Poll\Models\Poll;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetPollAction
{
    public function __construct(
        private CloseExpiredPollAction $closeExpiredPollAction
    ) {}

    public function __invoke(Guild $guild, int $pollId): Poll
    {
        $poll = Poll::query()
            ->where('guild_id', $guild->id)
            ->where('id', $pollId)
            ->with(['options' => fn ($q) => $q->withCount('votes')->with(['votes' => fn ($q2) => $q2->with('character:id,name')]), 'creatorCharacter:id,name'])
            ->first();

        if ($poll === null) {
            throw new ModelNotFoundException('Голосование не найдено.');
        }

        ($this->closeExpiredPollAction)($poll);

        return $poll;
    }
}
