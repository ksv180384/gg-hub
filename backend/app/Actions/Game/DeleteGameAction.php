<?php

namespace App\Actions\Game;

use App\Models\Game;

class DeleteGameAction
{
    public function __invoke(Game $game): void
    {
        $game->delete();
    }
}
