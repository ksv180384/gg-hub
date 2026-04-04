<?php

namespace Domains\Poll\Actions;

use Domains\Poll\Models\Poll;

class ClosePollAction
{
    public function __invoke(Poll $poll): Poll
    {
        $poll->is_closed = true;
        $poll->closed_at = now();
        $poll->save();

        return $poll->fresh(['options' => fn ($q) => $q->withCount('votes')->with(['votes' => fn ($q2) => $q2->with('character:id,name')]), 'creatorCharacter:id,name']);
    }
}
