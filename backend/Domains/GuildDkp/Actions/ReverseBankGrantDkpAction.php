<?php

namespace Domains\GuildDkp\Actions;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;

class ReverseBankGrantDkpAction
{
    public function __construct(
        private RecordGuildDkpLedgerEntryAction $recordGuildDkpLedgerEntryAction,
        private ResolveGuildMemberUserIdAction $resolveGuildMemberUserIdAction,
    ) {}

    public function __invoke(Guild $guild, GuildBankItemGrant $grant, ?User $actor): void
    {
        if (! (bool) ($guild->dkp_enabled ?? false)) {
            return;
        }

        $charged = (int) ($grant->dkp_charged ?? 0);
        if ($charged <= 0) {
            return;
        }

        $userId = ($this->resolveGuildMemberUserIdAction)($guild, (int) $grant->received_by_character_id);

        ($this->recordGuildDkpLedgerEntryAction)($guild, [
            'user_id' => $userId,
            'amount' => $charged,
            'occurred_at' => now(),
            'source' => GuildDkpLedgerSource::BankGrantRevoke,
            'guild_bank_item_grant_id' => $grant->id,
            'guild_bank_item_id' => (int) $grant->guild_bank_item_id,
            'character_id' => (int) $grant->received_by_character_id,
            'actor_user_id' => $actor?->id,
            'reason' => $grant->reason !== '' ? $grant->reason : null,
        ]);
    }
}
