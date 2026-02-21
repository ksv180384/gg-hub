<?php

namespace Domains\Raid\Actions;

use Domains\Raid\Models\Raid;

class CreateRaidAction
{
    public function __invoke(array $data): Raid
    {
        return Raid::create($data);
    }
}
