<?php

namespace Domains\Poll\Actions;

use Domains\Poll\Models\Poll;

class ResetPollAction
{
    public function __invoke(Poll $poll): Poll
    {
        $poll->votes()->delete();
        $poll->is_closed = false;
        $poll->closed_at = null;
        $poll->save();

        return $poll->fresh(['options' => fn ($q) => $q->withCount('votes')->with(['votes' => fn ($q2) => $q2->with('character:id,name')]), 'creatorCharacter:id,name']);
    }
}
