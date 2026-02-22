<?php

namespace App\Actions\Server;

use App\Models\Game;
use App\Models\Localization;
use App\Models\Server;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateServerAction
{
    /**
     * @param array{name: string, slug: string, is_active?: bool} $data
     */
    public function __invoke(Game $game, Localization $localization, array $data): Server
    {
        if ($localization->game_id !== (int) $game->id) {
            throw new HttpException(404, 'Локализация не принадлежит этой игре.');
        }
        return $localization->servers()->create([
            'game_id' => $game->id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
