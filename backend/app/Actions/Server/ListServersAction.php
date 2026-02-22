<?php

namespace App\Actions\Server;

use App\Models\Game;
use App\Models\Localization;
use App\Models\Server;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ListServersAction
{
    /**
     * @return Collection<int, Server>
     */
    public function __invoke(Game $game, Localization $localization): Collection
    {
        if ($localization->game_id !== (int) $game->id) {
            throw new HttpException(404, 'Локализация не принадлежит этой игре.');
        }
        return $localization->servers()
            ->whereNull('merged_into_server_id')
            ->orderBy('name')
            ->get();
    }
}
