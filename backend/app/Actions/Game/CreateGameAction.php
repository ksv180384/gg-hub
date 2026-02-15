<?php

namespace App\Actions\Game;

use App\Contracts\Repositories\GameRepositoryInterface;
use App\Models\Game;

class CreateGameAction
{
    public function __construct(
        private GameRepositoryInterface $gameRepository
    ) {}

    public function execute(array $data): Game
    {
        return $this->gameRepository->create($data);
    }
}
