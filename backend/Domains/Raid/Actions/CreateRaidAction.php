<?php

namespace Domains\Raid\Actions;

use Domains\Raid\Models\Raid;

class CreateRaidAction
{
    public function execute(array $data): Raid
    {
        return Raid::create($data);
    }
}
