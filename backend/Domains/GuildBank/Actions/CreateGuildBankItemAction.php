<?php

namespace Domains\GuildBank\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;

class CreateGuildBankItemAction
{
    /** @param array{name:string,description?:?string,guild_bank_item_tier_id?:?int,dkp_cost?:?int,quantity?:?int} $data */
    public function __invoke(Guild $guild, array $data): GuildBankItem
    {
        $item = new GuildBankItem();
        $item->fill($data);
        $item->guild_id = $guild->id;
        $item->save();

        return $item->load('tier');
    }
}

