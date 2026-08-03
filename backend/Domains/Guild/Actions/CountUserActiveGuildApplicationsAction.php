<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Enums\GuildApplicationStatus;
use Domains\Guild\Models\GuildApplication;
use Domains\User\Models\User;

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
