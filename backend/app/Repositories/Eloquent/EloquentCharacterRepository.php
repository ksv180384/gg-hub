<?php

namespace App\Repositories\Eloquent;

use App\Filters\CharacterFilter;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

class EloquentCharacterRepository
{
    public function getByGameWithContext(int $gameId): Collection
    {
        return Character::query()
            ->where('game_id', $gameId)
            ->with(['localization', 'server', 'gameClasses', 'user'])
            ->orderBy('name')
            ->get();
    }

    public function findByIdAndGame(int $id, int $gameId): ?Character
    {
        return Character::query()
            ->where('id', $id)
            ->where('game_id', $gameId)
            ->with([
                'game',
                'localization',
                'server',
                'gameClasses',
                'tags' => fn ($q) => $q->notHidden()->with(['usedByUser', 'createdByUser']),
                'guildMember.guild',
                'user',
            ])
            ->first();
    }

    public function getByUserWithContext(int $userId, CharacterFilter $filter): Collection
    {
        $query = Character::query()
            ->where('user_id', $userId)
            ->with([
                'game',
                'localization',
                'server',
                'gameClasses',
                'tags' => fn ($q) => $q->notHidden()->with(['usedByUser', 'createdByUser']),
                'guildMember.guild',
                'user',
            ]);
        $query->filter($filter);

        return $query->get();
    }

    public function getByUserAvailableForGuildLeader(int $userId, CharacterFilter $filter): Collection
    {
        $leaderIds = Guild::query()
            ->whereNotNull('leader_character_id')
            ->pluck('leader_character_id');

        return Character::query()
            ->where('user_id', $userId)
            ->filter($filter)
            ->whereDoesntHave('guildMember')
            ->whereNotIn('id', $leaderIds)
            ->with([
                'game',
                'localization',
                'server',
                'gameClasses',
                'tags' => fn ($q) => $q->notHidden()->with(['usedByUser', 'createdByUser']),
                'user',
            ])
            ->get();
    }

    public function findByIdAndUser(int $id, int $userId): ?Character
    {
        return Character::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->with([
                'game',
                'localization',
                'server',
                'gameClasses',
                'tags' => fn ($q) => $q->notHidden()->with(['usedByUser', 'createdByUser']),
                'guildMember.guild',
                'user',
            ])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Character
    {
        return Character::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Character $character, array $data): Character
    {
        $character->update($data);

        return $character->fresh();
    }
}
