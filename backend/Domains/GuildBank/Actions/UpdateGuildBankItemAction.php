<?php

namespace Domains\GuildBank\Actions;

use Domains\GuildBank\Models\GuildBankItem;

class UpdateGuildBankItemAction
{
    /** @param array{name?:string,description?:?string,tier?:?string,color?:?string,dkp_cost?:?int,quantity?:?int} $data */
    public function __invoke(GuildBankItem $item, array $data): GuildBankItem
    {
        $item->fill($data);
        $item->save();

        return $item;
    }
}

