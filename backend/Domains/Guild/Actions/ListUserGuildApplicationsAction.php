<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUserGuildApplicationsAction
{
    public function __invoke(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return GuildApplication::query()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->with(['guild', 'character.gameClasses', 'character.game', 'invitedByCharacter', 'revokedByCharacter'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}

