<?php

namespace Domains\Game\Actions;

use App\Repositories\Eloquent\EloquentGameRepository;
use Domains\Game\Models\Game;

class CreateGameAction
{
    public function __construct(
        private EloquentGameRepository $gameRepository
    ) {}

    public function __invoke(array $data): Game
    {
        return $this->gameRepository->create($data);
    }
}
