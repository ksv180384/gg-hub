<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListGuildApplicationsAction
{
    public function __invoke(Guild $guild, int $perPage = 20): LengthAwarePaginator
    {
        return $guild->applications()
            ->with(['character', 'invitedByCharacter'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
