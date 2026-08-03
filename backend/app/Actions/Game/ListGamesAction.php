<?php

namespace App\Actions\Game;

use App\Repositories\Eloquent\EloquentGameRepository;
use Domains\Game\Models\Game;
use Illuminate\Support\Collection;

class ListGamesAction
{
    public function __construct(
        private EloquentGameRepository $gameRepository
    ) {}

    /**
     * @return Collection<int, Game>
     */
    public function __invoke(): Collection
    {
        return $this->gameRepository->getActive()->load('localizations');
    }
}
