<?php

namespace App\Actions\Game;

use App\Models\Game;

class GetGameAction
{
    public function __invoke(Game $game): Game
    {
        $game->load([
            'localizations' => fn ($q) => $q->with([
                'servers' => fn ($q) => $q->whereNull('merged_into_server_id')->orderBy('name'),
            ]),
            'gameClasses',
        ]);
        return $game;
    }
}
