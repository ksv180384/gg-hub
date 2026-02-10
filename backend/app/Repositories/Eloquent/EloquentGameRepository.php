<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\GameRepositoryInterface;
use Domains\Game\Models\Game;
use Illuminate\Support\Collection;

class EloquentGameRepository implements GameRepositoryInterface
{
    public function getActive(): Collection
    {
        return Game::query()
            ->where('is_active', true)
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Game
    {
        return Game::create($data);
    }
}
