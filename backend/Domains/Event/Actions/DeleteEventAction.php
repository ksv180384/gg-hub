<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\Event;

class DeleteEventAction
{
    public function __invoke(Event $event): void
    {
        $event->delete();
    }
}
