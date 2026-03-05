<?php

namespace Domains\Raid\Actions;

use Domains\Raid\Models\Raid;

class DeleteRaidAction
{
    /**
     * Удаляет рейд и всех дочерних (каскадно через БД).
     */
    public function __invoke(Raid $raid): void
    {
        $raid->delete();
    }
}
