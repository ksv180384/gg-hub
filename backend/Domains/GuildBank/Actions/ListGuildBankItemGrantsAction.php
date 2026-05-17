<?php

namespace Domains\GuildBank\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Database\Eloquent\Collection;

class ListGuildBankItemGrantsAction
{
    /** @return Collection<int, GuildBankItemGrant> */
    public function __invoke(Guild $guild, GuildBankItem $item): Collection
    {
        return GuildBankItemGrant::query()
            ->where('guild_id', $guild->id)
            ->where('guild_bank_item_id', $item->id)
            ->with([
                'receivedByCharacter:id,name',
                'grantedByCharacter:id,name',
            ])
            ->orderByDesc('granted_at')
            ->get();
    }
}

