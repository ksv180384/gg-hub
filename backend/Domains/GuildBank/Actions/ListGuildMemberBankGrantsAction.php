<?php

namespace Domains\GuildBank\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Database\Eloquent\Collection;

class ListGuildMemberBankGrantsAction
{
    /** @return Collection<int, GuildBankItemGrant> */
    public function __invoke(Guild $guild, int $characterId): Collection
    {
        return GuildBankItemGrant::query()
            ->where('guild_id', $guild->id)
            ->where('received_by_character_id', $characterId)
            ->with([
                'item:id,guild_id,name,guild_bank_item_tier_id,dkp_cost,quantity',
                'item.tier',
                'grantedByCharacter:id,name',
            ])
            ->orderByDesc('granted_at')
            ->get();
    }
}

