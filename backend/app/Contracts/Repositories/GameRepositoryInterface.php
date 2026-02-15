<?php

namespace App\Contracts\Repositories;

use App\Models\Game;
use Illuminate\Support\Collection;

interface GameRepositoryInterface
{
    /**
     * Список активных игр.
     *
     * @return Collection<int, Game>
     */
    public function getActive(): Collection;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Game;
}
