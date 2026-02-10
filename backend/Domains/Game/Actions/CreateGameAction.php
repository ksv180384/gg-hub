<?php

namespace Domains\Game\Actions;

use App\Contracts\Repositories\GameRepositoryInterface;
use Domains\Game\Models\Game;

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
