<?php

namespace Domains\GuildBank\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Illuminate\Database\Eloquent\Collection;

class ListGuildBankItemsAction
{
    /** @return Collection<int, GuildBankItem> */
    public function __invoke(Guild $guild): Collection
    {
        return GuildBankItem::query()
            ->where('guild_id', $guild->id)
            ->with('tier:id,name,color')
            ->withCount('grants')
            ->orderBy('name')
            ->get();
    }
}

