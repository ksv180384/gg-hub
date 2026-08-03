<?php

namespace App\Actions\GameClass;

use Domains\Game\Models\GameClass;

class DeleteGameClassAction
{
    public function __invoke(GameClass $gameClass): void
    {
        $gameClass->delete();
    }
}
