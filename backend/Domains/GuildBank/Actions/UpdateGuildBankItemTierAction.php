<?php

namespace Domains\GuildBank\Actions;

use Domains\GuildBank\Models\GuildBankItemTier;

class UpdateGuildBankItemTierAction
{
    /** @param array{name:string,color:string} $data */
    public function __invoke(GuildBankItemTier $tier, array $data): GuildBankItemTier
    {
        $tier->fill($data);
        $tier->save();

        return $tier->loadCount('items');
    }
}
