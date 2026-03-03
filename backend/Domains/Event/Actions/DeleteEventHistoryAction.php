<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistory;

class DeleteEventHistoryAction
{
    public function __invoke(EventHistory $history): void
    {
        $history->delete();
    }
}

