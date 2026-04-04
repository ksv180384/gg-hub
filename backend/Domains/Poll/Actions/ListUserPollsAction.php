<?php

namespace Domains\Poll\Actions;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Guild\Models\GuildMember;
use Domains\Poll\Models\Poll;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Активные голосования и закрытые не более 3 суток назад из гильдий пользователя.
 * Опционально фильтр по game_id.
 */
class ListUserPollsAction
{
    public function __construct(
        private CloseExpiredPollAction $closeExpiredPollAction
    ) {}

    public function __invoke(User $user, ?int $gameId = null): Collection
    {
        $userCharacterIds = Character::query()
            ->where('user_id', $user->id)
            ->when($gameId !== null, fn ($q) => $q->where('game_id', $gameId))
            ->pluck('id');

        if ($userCharacterIds->isEmpty()) {
            return new Collection([]);
        }

        $guildIds = GuildMember::query()
            ->whereIn('character_id', $userCharacterIds)
            ->distinct()
            ->pluck('guild_id');

        if ($guildIds->isEmpty()) {
            return new Collection([]);
        }

        $threeDaysAgo = Carbon::now()->subDays(3);

        return Poll::query()
            ->whereIn('guild_id', $guildIds)
            ->where(function ($q) use ($threeDaysAgo) {
                $q->where('is_closed', false)
                    ->orWhere(function ($q2) use ($threeDaysAgo) {
                        $q2->where('is_closed', true)
                            ->where(function ($q3) use ($threeDaysAgo) {
                                $q3->where('closed_at', '>=', $threeDaysAgo)
                                    ->orWhere(function ($q4) use ($threeDaysAgo) {
                                        $q4->whereNull('closed_at')->where('updated_at', '>=', $threeDaysAgo);
                                    });
                            });
                    });
            })
            ->with(['options' => fn ($q) => $q->withCount('votes')->with(['votes' => fn ($q2) => $q2->with('character:id,name')]), 'creatorCharacter:id,name', 'guild:id,name'])
            ->orderByRaw('is_closed ASC')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($polls as $poll) {
            ($this->closeExpiredPollAction)($poll);
        }

        return $polls;
    }
}
