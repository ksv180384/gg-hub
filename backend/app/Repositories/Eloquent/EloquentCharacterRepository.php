<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use Domains\Character\Models\Character;
use Illuminate\Support\Collection;

class EloquentCharacterRepository implements CharacterRepositoryInterface
{
    public function getByUserWithContext(int $userId): Collection
    {
        return Character::query()
            ->where('user_id', $userId)
            ->with(['game', 'localization', 'server'])
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Character
    {
        return Character::create($data);
    }
}
