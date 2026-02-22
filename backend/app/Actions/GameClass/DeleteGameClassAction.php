<?php

namespace App\Actions\GameClass;

use App\Models\GameClass;

class DeleteGameClassAction
{
    public function __invoke(GameClass $gameClass): void
    {
        $gameClass->delete();
    }
}
