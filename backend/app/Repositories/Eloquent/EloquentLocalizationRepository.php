<?php

namespace App\Repositories\Eloquent;

use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;

class EloquentLocalizationRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createForGame(Game $game, array $data): Localization
    {
        return $game->localizations()->create($data + ['is_active' => true]);
    }
}
