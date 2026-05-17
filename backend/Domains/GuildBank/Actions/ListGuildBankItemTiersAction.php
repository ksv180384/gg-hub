<?php

namespace Domains\GuildBank\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItemTier;
use Illuminate\Database\Eloquent\Collection;

class ListGuildBankItemTiersAction
{
    /** @return Collection<int, GuildBankItemTier> */
    public function __invoke(Guild $guild): Collection
    {
        return GuildBankItemTier::query()
            ->where('guild_id', $guild->id)
            ->withCount('items')
            ->orderBy('name')
            ->get();
    }
}
