<?php

namespace Domains\GuildBank\Actions;

use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Support\Facades\DB;

class RevokeGuildBankItemGrantAction
{
    public function __invoke(GuildBankItemGrant $grant): void
    {
        DB::transaction(function () use ($grant): void {
            /** @var GuildBankItemGrant $lockedGrant */
            $lockedGrant = GuildBankItemGrant::query()
                ->whereKey($grant->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var GuildBankItem $item */
            $item = GuildBankItem::query()
                ->whereKey($lockedGrant->guild_bank_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->quantity !== null) {
                $item->quantity = (int) $item->quantity + 1;
                $item->save();
            }

            $lockedGrant->delete();
        });
    }
}
