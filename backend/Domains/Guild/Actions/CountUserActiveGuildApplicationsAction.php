<?php

namespace Domains\Guild\Actions;

use App\Models\User;
use Domains\Guild\Enums\GuildApplicationStatus;
use Domains\Guild\Models\GuildApplication;

class CountUserActiveGuildApplicationsAction
{
    public function __invoke(User $user): int
    {
        return GuildApplication::query()
            ->whereHas('character', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', [
                GuildApplicationStatus::Pending->value,
                GuildApplicationStatus::Invitation->value,
            ])
            ->count();
    }
}

