<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\LocalizationRepositoryInterface;
use App\Models\Game;
use App\Models\Localization;

class EloquentLocalizationRepository implements LocalizationRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function createForGame(Game $game, array $data): Localization
    {
        return $game->localizations()->create($data + ['is_active' => true]);
    }
}
