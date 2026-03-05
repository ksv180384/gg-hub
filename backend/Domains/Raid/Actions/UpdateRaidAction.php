<?php

namespace Domains\Raid\Actions;

use Domains\Raid\Models\Raid;

class UpdateRaidAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Raid $raid, array $data): Raid
    {
        $raid->update($data);
        return $raid->fresh(['leader:id,name', 'parent:id,name']);
    }
}
