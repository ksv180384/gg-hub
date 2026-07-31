<?php

namespace App\Actions\Game;

use Domains\Game\Models\Game;

class DeleteGameAction
{
    public function __invoke(Game $game): void
    {
        $game->delete();
    }
}
