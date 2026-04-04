<?php

namespace Domains\Poll\Actions;

use Domains\Poll\Models\Poll;

/**
 * Закрывает голосование, если истёк срок (ends_at в прошлом).
 */
class CloseExpiredPollAction
{
    public function __invoke(Poll $poll): void
    {
        if ($poll->is_closed) {
            return;
        }

        if (! $poll->ends_at || ! now()->gt($poll->ends_at)) {
            return;
        }

        $poll->is_closed = true;
        $poll->closed_at = now();
        $poll->save();
    }
}
