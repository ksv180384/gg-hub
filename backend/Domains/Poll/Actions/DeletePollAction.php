<?php

namespace Domains\Poll\Actions;

use Domains\Poll\Models\Poll;

class DeletePollAction
{
    public function __invoke(Poll $poll): void
    {
        $poll->delete();
    }
}
