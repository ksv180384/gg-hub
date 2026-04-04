<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Illuminate\Support\Collection;

/**
 * Персонажи текущего пользователя в гильдии (для выбора при комментировании).
 */
final class GetUserGuildCharactersAction
{
    public function __invoke(User $user, Guild $guild): Collection
    {
        return $guild->members()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->with('character')
            ->orderBy('joined_at')
            ->get()
            ->map(fn ($m) => $m->character)
            ->filter();
    }
}
