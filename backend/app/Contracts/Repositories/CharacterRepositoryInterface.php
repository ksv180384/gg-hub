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
     * Персонажи пользователя на указанном сервере, которые не состоят ни в какой гильдии
     * и не являются лидером другой гильдии (для выбора лидера гильдии).
     *
     * @return Collection<int, Character>
     */
    public function getByUserAvailableForGuildLeader(int $userId, int $gameId, int $serverId): Collection;

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
