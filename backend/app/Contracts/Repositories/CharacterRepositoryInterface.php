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
    public function getByUserWithContext(int $userId, ?int $gameId = null): Collection;

    /**
     * Персонаж по id, принадлежащий пользователю.
     */
    public function findByIdAndUser(int $id, int $userId): ?Character;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Character;

    /**
     * @param array<string, mixed> $data
     */
    public function update(Character $character, array $data): Character;
}
