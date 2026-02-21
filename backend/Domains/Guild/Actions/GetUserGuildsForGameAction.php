<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

class GetUserGuildsForGameAction
{
    /**
     * Гильдии текущей игры, в которых состоит пользователь (хотя бы один его персонаж в гильдии).
     * Для каждой гильдии добавлено is_leader: true если лидер гильдии — персонаж этого пользователя.
     *
     * @return Collection<int, array{id: int, name: string, is_leader: bool}>
     */
    public function __invoke(User $user, int $gameId): Collection
    {
        $userCharacterIds = Character::query()
            ->where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->pluck('id');

        if ($userCharacterIds->isEmpty()) {
            return collect();
        }

        $guilds = Guild::query()
            ->where('game_id', $gameId)
            ->whereHas('members', function ($q) use ($userCharacterIds) {
                $q->whereIn('character_id', $userCharacterIds);
            })
            ->with('leader')
            ->orderBy('name')
            ->get();

        return $guilds->map(function (Guild $guild) use ($user) {
            return [
                'id' => $guild->id,
                'name' => $guild->name,
                'is_leader' => $guild->leader_character_id && $guild->leader &&
                    (int) $guild->leader->user_id === (int) $user->id,
            ];
        });
    }
}
