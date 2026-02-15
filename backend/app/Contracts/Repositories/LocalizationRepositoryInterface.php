<?php

namespace App\Contracts\Repositories;

use App\Models\Game;
use App\Models\Localization;

interface LocalizationRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function createForGame(Game $game, array $data): Localization;
}
