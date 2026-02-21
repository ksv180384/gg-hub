<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

class EloquentCharacterRepository implements CharacterRepositoryInterface
{
    public function getByUserWithContext(int $userId, ?int $gameId = null): Collection
    {
        $query = Character::query()
            ->where('user_id', $userId)
            ->with(['game', 'localization', 'server', 'gameClasses', 'tags', 'guildMember.guild']);
        if ($gameId !== null) {
            $query->where('game_id', $gameId);
        }
        return $query->get();
    }

    public function getByUserAvailableForGuildLeader(int $userId, int $gameId, int $serverId): Collection
    {
        $leaderIds = Guild::query()
            ->whereNotNull('leader_character_id')
            ->pluck('leader_character_id');

        return Character::query()
            ->where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('server_id', $serverId)
            ->whereDoesntHave('guildMember')
            ->whereNotIn('id', $leaderIds)
            ->with(['game', 'localization', 'server', 'gameClasses', 'tags'])
            ->get();
    }

    public function findByIdAndUser(int $id, int $userId): ?Character
    {
        return Character::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->with(['game', 'localization', 'server', 'gameClasses', 'tags', 'guildMember.guild'])
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Character
    {
        return Character::create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Character $character, array $data): Character
    {
        $character->update($data);
        return $character->fresh();
    }
}
