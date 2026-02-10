<?php

namespace App\Contracts\Repositories;

use Domains\Character\Models\Character;
use Illuminate\Support\Collection;

interface CharacterRepositoryInterface
{
    /**
     * Персонажи пользователя с игрой, локализацией и сервером.
     *
     * @return Collection<int, Character>
     */
    public function getByUserWithContext(int $userId): Collection;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Character;
}
