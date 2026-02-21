<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\Event;

class CreateEventAction
{
    public function __invoke(array $data): Event
    {
        return Event::create($data);
    }
}
