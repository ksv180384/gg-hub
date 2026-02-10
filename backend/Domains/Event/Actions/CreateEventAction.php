<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\Event;

class CreateEventAction
{
    public function execute(array $data): Event
    {
        return Event::create($data);
    }
}
