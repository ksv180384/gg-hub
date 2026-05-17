<?php

namespace Domains\GuildBank\Actions;

use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItemTier;

class CreateGuildBankItemTierAction
{
    /** @param array{name:string,color:string} $data */
    public function __invoke(Guild $guild, array $data): GuildBankItemTier
    {
        $tier = new GuildBankItemTier();
        $tier->fill($data);
        $tier->guild_id = $guild->id;
        $tier->save();

        return $tier->loadCount('items');
    }
}
