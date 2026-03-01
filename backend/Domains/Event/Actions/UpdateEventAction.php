<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\Event;

class UpdateEventAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Event $event, array $data): Event
    {
        $event->update($data);
        return $event->fresh(['creator:id,name']);
    }
}
