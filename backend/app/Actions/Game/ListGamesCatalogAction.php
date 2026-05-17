<?php

namespace App\Actions\Game;

use App\Contracts\Repositories\GameRepositoryInterface;
use Illuminate\Support\Collection;

class ListGamesCatalogAction
{
    public function __construct(
        private GameRepositoryInterface $gameRepository
    ) {}

    /**
     * @return Collection<int, \App\Models\Game>
     */
    public function __invoke(): Collection
    {
        return $this->gameRepository->getActiveCatalog();
    }
}

