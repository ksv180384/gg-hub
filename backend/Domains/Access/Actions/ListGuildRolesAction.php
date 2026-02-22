<?php

namespace Domains\Access\Actions;

use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Collection;
use Domains\Access\Models\GuildRole;

class ListGuildRolesAction
{
    /**
     * @return Collection<int, GuildRole>
     */
    public function __invoke(Guild $guild): Collection
    {
        return $guild->roles()->with('permissions')->orderByDesc('priority')->orderBy('name')->get();
    }
}
