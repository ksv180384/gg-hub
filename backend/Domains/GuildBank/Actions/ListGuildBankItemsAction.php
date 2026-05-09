<?php

namespace Domains\GuildBank\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Database\Eloquent\Collection;

class ListGuildBankItemsAction
{
    /** @return Collection<int, GuildBankItem> */
    public function __invoke(Guild $guild): Collection
    {
        return GuildBankItem::query()
            ->where('guild_id', $guild->id)
            ->withCount('grants')
            ->addSelect([
                'last_granted_at' => GuildBankItemGrant::query()
                    ->select('granted_at')
                    ->whereColumn('guild_bank_item_id', 'guild_bank_items.id')
                    ->latest('granted_at')
                    ->limit(1),
            ])
            ->orderBy('name')
            ->get();
    }
}

