<?php

namespace App\Contracts\Repositories;

use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;

interface LocalizationRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function createForGame(Game $game, array $data): Localization;
}
