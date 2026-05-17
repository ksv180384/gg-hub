<?php

namespace Domains\GuildBank\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Actions\ReverseBankGrantDkpAction;
use Illuminate\Support\Facades\DB;

class RevokeGuildBankItemGrantAction
{
    public function __construct(
        private ReverseBankGrantDkpAction $reverseBankGrantDkpAction,
    ) {}

    public function __invoke(Guild $guild, GuildBankItemGrant $grant, ?User $actor = null): void
    {
        DB::transaction(function () use ($guild, $grant, $actor): void {
            /** @var GuildBankItemGrant $lockedGrant */
            $lockedGrant = GuildBankItemGrant::query()
                ->whereKey($grant->id)
                ->lockForUpdate()
                ->firstOrFail();

            ($this->reverseBankGrantDkpAction)($guild, $lockedGrant, $actor);

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
